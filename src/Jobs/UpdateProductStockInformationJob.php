<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;

class UpdateProductStockInformationJob implements ShouldQueue
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
        $this->product->calculateStock();
        $this->product->calculateTotalPurchases();

        if ($this->product->is_bundle) {
            $this->product->calculateDeliveryTime();
        }

        foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $this->product->id)->pluck('product_id') as $productId) {
            $bundleParentProduct = Product::find($productId);
            if ($bundleParentProduct) {
                $bundleParentProduct->calculateStock();
                $bundleParentProduct->calculateTotalPurchases();
                $bundleParentProduct->calculateDeliveryTime();
            }
        }

        $this->product->productGroup->total_stock = $this->product->productGroup->products->sum('total_stock');
        $this->product->productGroup->total_purchases = $this->product->productGroup->products->sum('total_purchases');
        $this->product->productGroup->saveQuietly();
    }
}
