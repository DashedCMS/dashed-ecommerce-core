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
        foreach($this->product->childProducts as $childProduct) {
            $childProduct->calculateStock();
        }

        $this->product->calculateStock();

        if($this->product->parent) {
            $this->product->parent->calculateStock();
        }

        foreach(DB::table('dashed__product_bundle_products')->where('bundle_product_id', $this->product->id)->pluck('product_id') as $productId) {
            $bundleParentProduct = Product::find($productId);
            $bundleParentProduct->calculateStock();
        }

        foreach($this->product->childProducts as $childProduct) {
            $childProduct->calculateInStock();
        }

        $this->product->calculateInStock();

        if($this->product->parent) {
            $this->product->parent->calculateInStock();
        }

        foreach(DB::table('dashed__product_bundle_products')->where('bundle_product_id', $this->product->id)->pluck('product_id') as $productId) {
            $bundleParentProduct = Product::find($productId);
            $bundleParentProduct->calculateInStock();
        }
    }
}
