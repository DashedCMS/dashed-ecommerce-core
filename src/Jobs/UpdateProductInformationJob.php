<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;

class UpdateProductInformationJob implements ShouldQueue
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
        foreach ($this->product->childProducts as $childProduct) {
            $childProduct->calculateStock();
            $childProduct->calculateTotalPurchases();
        }

        $this->product->calculateStock();
        $childProduct->calculateTotalPurchases();

        if ($this->product->parent) {
            $this->product->parent->calculateStock();
            $childProduct->calculateTotalPurchases();
        }

        foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $this->product->id)->pluck('product_id') as $productId) {
            $bundleParentProduct = Product::find($productId);
            if ($bundleParentProduct) {
                $bundleParentProduct->calculateStock();
                $childProduct->calculateTotalPurchases();
            }
        }

        foreach ($this->product->childProducts as $childProduct) {
            $childProduct->calculateInStock();
            $childProduct->calculateTotalPurchases();
        }

        $this->product->calculateInStock();
        $childProduct->calculateTotalPurchases();

        if ($this->product->parent) {
            $this->product->parent->calculateInStock();
            $childProduct->calculateTotalPurchases();
        }

        foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $this->product->id)->pluck('product_id') as $productId) {
            $bundleParentProduct = Product::find($productId);
            if ($bundleParentProduct) {
                $bundleParentProduct->calculateInStock();
                $childProduct->calculateTotalPurchases();
            }
        }
    }
}
