<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedCore\Classes\Caching\CacheInvalidator;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;

class UpdateProductPricesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HandlesQueueFailures;

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
        $products = $this->productGroup->products;

        foreach ($products as $product) {
            $product->calculatePrices();

            foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $product->id)->pluck('product_id') as $productId) {
                $bundleParentProduct = Product::find($productId);
                if ($bundleParentProduct) {
                    $bundleParentProduct->calculatePrices();
                }
            }

        }

        // calculatePrices() raakt geen van deze vier velden, dus één keer na de
        // lus geeft dezelfde uitkomst. Een groep zonder producten blijft staan.
        if ($products->isNotEmpty()) {
            $this->productGroup->min_price = $products->min('price');
            $this->productGroup->max_price = $products->max('price');
            $this->productGroup->total_stock = $products->sum('total_stock');
            $this->productGroup->total_purchases = $products->sum('total_purchases');
            $this->productGroup->saveQuietly();

            foreach ($this->productGroup->volumeDiscounts as $volumeDiscount) {
                $volumeDiscount->connectAllProducts();
            }
        }

        if (class_exists(CacheInvalidator::class)) {
            CacheInvalidator::flushSite();
        }
    }
}
