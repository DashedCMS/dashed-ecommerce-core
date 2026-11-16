<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Support\BackgroundTasks;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Dashed\DashedEcommerceCore\Classes\ProductVariationNaming;
use Dashed\DashedEcommerceCore\Classes\VariationPriceSuggester;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;

class CreateMissingProductVariationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TASK_TYPE = 'product-variations';

    public $tries = 5;
    public $timeout = 1200;

    public ProductGroup $productGroup;

    /**
     * Optionele selectie van variaties om aan te maken. Elke variatie is een
     * lijst van product_filter_option_id's. Null = alle ontbrekende variaties.
     *
     * @var array<int, array<int, int>>|null
     */
    public ?array $variations = null;

    /**
     * Prijs per variatie, sleutel = optie-id's met '-' (zie VariationPriceSuggester).
     *
     * @var array<string, array{price: float, new_price: float|null}>
     */
    public array $prices = [];

    /**
     * De beheerder die de variaties liet aanmaken, voor het takenicoon.
     */
    public ?int $startedByUserId = null;

    /**
     * Create a new job instance.
     */
    public function __construct(ProductGroup $productGroup, ?array $variations = null, array $prices = [], ?int $startedByUserId = null)
    {
        $this->productGroup = $productGroup;
        $this->variations = $variations;
        $this->prices = $prices;
        $this->startedByUserId = $startedByUserId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $missingVariations = $this->variations ?? $this->productGroup->missingVariations();

        if (! count($missingVariations) && ! $this->productGroup->products->count()) {
            $missingVariations = [
                [],
            ];
        }

        ProductVariationNaming::flushOptionCache();

        // Een enkele variatie is zo klaar en krijgt geen taak.
        $task = count($missingVariations) > 1
            ? BackgroundTasks::start(
                self::TASK_TYPE,
                __('Variaties aanmaken: :naam', ['naam' => $this->productGroup->name]),
                count($missingVariations),
                $this->startedByUserId,
                $this->job?->uuid(),
            )
            : null;
        $created = 0;

        foreach ($missingVariations as $missingVariation) {
            $optionIds = array_values($missingVariation);

            $newProduct = new Product();
            $newProduct->site_ids = $this->productGroup->site_ids;
            foreach (Locales::getLocales() as $locale) {
                $newProduct->setTranslation('name', $locale['id'], ProductVariationNaming::name($this->productGroup, $optionIds, $locale['id']));
                $newProduct->setTranslation('slug', $locale['id'], ProductVariationNaming::slug($this->productGroup, $optionIds, $locale['id']));
            }
            $newProduct->sku = 'SKU' . rand(10000, 99999);
            while (Product::withTrashed()->where('sku', $newProduct->sku)->exists()) {
                $newProduct->sku = 'SKU' . rand(10000, 99999);
            }
            $newProduct->product_group_id = $this->productGroup->id;
            if ($price = $this->prices[VariationPriceSuggester::key($optionIds)] ?? null) {
                $newProduct->price = $price['price'];
                $newProduct->new_price = $price['new_price'] ?? null;
            }
            $newProduct->save();

            foreach ($missingVariation as $optionId) {
                DB::table('dashed__product_filter')->insert([
                    'product_id' => $newProduct->id,
                    'product_filter_id' => ProductFilterOption::find($optionId)->productFilter->id,
                    'product_filter_option_id' => $optionId,
                ]);
            }

            $created++;
            if ($created % 5 === 0) {
                $task?->progress($created);
            }
        }

        UpdateProductInformationJob::dispatch($this->productGroup, false);

        $task?->finish(
            __(':aantal variaties aangemaakt.', ['aantal' => $created]),
            rescue(fn () => ProductGroupResource::getUrl('edit', ['record' => $this->productGroup]), null, false),
            __('Productgroep bekijken'),
        );
    }

    public function failed(Throwable $e): void
    {
        BackgroundTasks::fail(self::TASK_TYPE, $e, $this->job?->uuid(), $this->startedByUserId);
    }
}
