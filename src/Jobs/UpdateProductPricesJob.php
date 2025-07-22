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

class UpdateProductPricesJob implements ShouldQueue
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
        foreach ($this->productGroup->products as $product) {
            $product->calculatePrices();

            foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $product->id)->pluck('product_id') as $productId) {
                $bundleParentProduct = Product::find($productId);
                if ($bundleParentProduct) {
                    $bundleParentProduct->calculatePrices();
                }
            }

            $this->productGroup->min_price = $this->productGroup->products->min('price');
            $this->productGroup->max_price = $this->productGroup->products->max('price');
            $this->productGroup->total_stock = $this->productGroup->products->sum('total_stock');
            $this->productGroup->total_purchases = $this->productGroup->products->sum('total_purchases');
            $this->productGroup->saveQuietly();

            foreach ($this->productGroup->volumeDiscounts as $volumeDiscount) {
                $volumeDiscount->connectAllProducts();
            }
        }
    }
}
