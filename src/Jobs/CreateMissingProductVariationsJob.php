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
     * Create a new job instance.
     */
    public function __construct(ProductGroup $productGroup)
    {
        $this->productGroup = $productGroup;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $missingVariations = $this->productGroup->missingVariations();

        foreach ($missingVariations as $missingVariation) {
            $newProduct = new Product();
            $newProduct->site_ids = $this->productGroup->site_ids;
            foreach (Locales::getLocales() as $locale) {
                $name = $this->productGroup->getTranslation('name', $locale['id']);
                foreach ($missingVariation as $optionId) {
                    $name .= ' | ' . ProductFilterOption::find($optionId)->getTranslation('name', $locale['id']);
                }
                $newProduct->setTranslation('name', $locale['id'], $name);
            }
            foreach (Locales::getLocales() as $locale) {
                $slug = $this->productGroup->getTranslation('slug', $locale['id']);
                foreach ($missingVariation as $optionId) {
                    $slug .= '-' . ProductFilterOption::find($optionId)->getTranslation('name', $locale['id']);
                }
                $newProduct->setTranslation('slug', $locale['id'], str($slug)->slug());
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

        UpdateProductInformationJob::dispatch($this->productGroup);
    }
}
