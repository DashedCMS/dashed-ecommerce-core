<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Dashed\DashedEcommerceCore\Classes\ProductVariationNaming;

class CreateMissingProductVariationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
     * Create a new job instance.
     */
    public function __construct(ProductGroup $productGroup, ?array $variations = null)
    {
        $this->productGroup = $productGroup;
        $this->variations = $variations;
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
            $newProduct->save();

            foreach ($missingVariation as $optionId) {
                DB::table('dashed__product_filter')->insert([
                    'product_id' => $newProduct->id,
                    'product_filter_id' => ProductFilterOption::find($optionId)->productFilter->id,
                    'product_filter_option_id' => $optionId,
                ]);
            }
        }

        UpdateProductInformationJob::dispatch($this->productGroup, false);
    }
}
