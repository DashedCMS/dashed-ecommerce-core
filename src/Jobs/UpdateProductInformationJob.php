<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Events\Products\ProductInformationUpdatedEvent;

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
            $childProduct->site_ids = $this->product->site_ids;
            $childProduct->saveQuietly();
        }

        if ($this->product->type == 'variable' && ! $this->product->parent_id && count($this->product->copyable_to_childs ?? [])) {
            foreach ($this->product->childProducts as $childProduct) {
                if (in_array('productCategories', $this->product->copyable_to_childs)) {
                    $childProduct->productCategories()->sync($this->product->productCategories);
                }
                if (in_array('shippingClasses', $this->product->copyable_to_childs)) {
                    $childProduct->shippingClasses()->sync($this->product->shippingClasses);
                }
                if (in_array('suggestedProducts', $this->product->copyable_to_childs)) {
                    $childProduct->suggestedProducts()->sync($this->product->suggestedProducts);
                }
                if (in_array('crossSellProducts', $this->product->copyable_to_childs)) {
                    $childProduct->crossSellProducts()->sync($this->product->crossSellProducts);
                }
                if (in_array('content', $this->product->copyable_to_childs)) {
                    $childProduct->content = $this->product->getOriginal('content');
                }
                if (in_array('description', $this->product->copyable_to_childs)) {
                    $childProduct->description = $this->product->getOriginal('description');
                }
                if (in_array('short_description', $this->product->copyable_to_childs)) {
                    $childProduct->short_description = $this->product->getOriginal('short_description');
                }
                if (in_array('images', $this->product->copyable_to_childs)) {
                    $childProduct->images = $this->product->getOriginal('images');
                }
                if (in_array('customBlocks', $this->product->copyable_to_childs)) {
                    if ($childProduct->customBlocks) {
                        $childProduct->customBlocks->delete();
                    }
                    $newCustomBlocks = $this->product->customBlocks->replicate();
                    $newCustomBlocks->blockable_id = $childProduct->id;
                    $newCustomBlocks->saveQuietly();
                }

                $childProduct->saveQuietly();
            }
        }

        foreach ($this->product->childProducts as $childProduct) {
            $childProduct->calculateStock();
            $childProduct->calculateTotalPurchases();
        }

        $this->product->calculateStock();
        $this->product->calculateTotalPurchases();
        $this->product->missing_variations = count($this->product->missingVariations());
        $this->product->saveQuietly();

        if ($this->product->parent) {
            $this->product->parent->calculateStock();
            $this->product->parent->calculateTotalPurchases();
        }

        foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $this->product->id)->pluck('product_id') as $productId) {
            $bundleParentProduct = Product::find($productId);
            if ($bundleParentProduct) {
                $bundleParentProduct->calculateStock();
                $bundleParentProduct->calculateTotalPurchases();
            }
        }

        ProductInformationUpdatedEvent::dispatch($this->product);
    }
}
