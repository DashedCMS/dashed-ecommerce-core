<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Models\ProductFilterOption;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;

class CreateMissingProductVariationsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public Product $product;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $missingVariations = $this->product->missingVariations();

        foreach ($missingVariations as $missingVariation) {
            $newProduct = new Product();
            $newProduct->site_ids = $this->product->site_ids;
            foreach (Locales::getLocales() as $locale) {
                $name = $this->product->getTranslation('name', $locale['id']);
                foreach ($missingVariation as $optionId) {
                    $name .= ' | ' . ProductFilterOption::find($optionId)->getTranslation('name', $locale['id']);
                }
                $newProduct->setTranslation('name', $locale['id'], $name);
            }
            foreach (Locales::getLocales() as $locale) {
                $slug = $this->product->getTranslation('slug', $locale['id']);
                foreach ($missingVariation as $optionId) {
                    $slug .= '-' . ProductFilterOption::find($optionId)->getTranslation('name', $locale['id']);
                }
                $newProduct->setTranslation('slug', $locale['id'], str($slug)->slug());
            }
            $newProduct->sku = 'SKU' . rand(10000, 99999);
            while (Product::withTrashed()->where('sku', $newProduct->sku)->exists()) {
                $newProduct->sku = 'SKU' . rand(10000, 99999);
            }
            $newProduct->type = 'variable';
            $newProduct->parent_id = $this->product->id;
            $newProduct->public = $this->product->public;
            $newProduct->save();

            foreach ($missingVariation as $optionId) {
                DB::table('dashed__product_filter')->insert([
                    'product_id' => $newProduct->id,
                    'product_filter_id' => ProductFilterOption::find($optionId)->productFilter->id,
                    'product_filter_option_id' => $optionId,
                ]);
            }

            UpdateProductInformationJob::dispatch($newProduct);
        }

        UpdateProductInformationJob::dispatch($this->product);
    }
}
