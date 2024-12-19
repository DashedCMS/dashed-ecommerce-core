<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

class UpdateProductCategoriesInformationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //Clear the cache
        $productCategories = ProductCategory::all();
        foreach ($productCategories as $productCategory) {
            foreach (Locales::getLocalesArray() as $locale => $name) {
                Cache::forget('product-category-url-' . $productCategory->id . '-' . $locale);
                Cache::forget('product-category-childs-' . $productCategory->id);
                Cache::forget('product-category-first-childs-' . $productCategory->id);
            }
        }

        //Warm the cache
        foreach ($productCategories as $productCategory) {
            foreach (Locales::getLocalesArray() as $locale => $name) {
                $productCategory->getUrl($locale);
                $productCategory->getChilds();
                $productCategory->getFirstChilds();
            }
        }
    }
}
