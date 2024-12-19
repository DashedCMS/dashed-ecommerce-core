<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
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
        $this->productGroup->missing_variations = $this->productGroup->missingVariations();
        $this->productGroup->saveQuietly();

//        if ($this->product->type == 'variable' && ! $this->product->parent_id && count($this->product->copyable_to_childs ?? [])) {
//            foreach ($this->product->childProducts as $childProduct) {
//                if (in_array('productCategories', $this->product->copyable_to_childs)) {
//                    $childProduct->productCategories()->sync($this->product->productCategories);
//                }
//                if (in_array('shippingClasses', $this->product->copyable_to_childs)) {
//                    $childProduct->shippingClasses()->sync($this->product->shippingClasses);
//                }
//                if (in_array('suggestedProducts', $this->product->copyable_to_childs)) {
//                    $childProduct->suggestedProducts()->sync($this->product->suggestedProducts);
//                }
//                if (in_array('crossSellProducts', $this->product->copyable_to_childs)) {
//                    $childProduct->crossSellProducts()->sync($this->product->crossSellProducts);
//                }
//                if (in_array('content', $this->product->copyable_to_childs)) {
//                    $childProduct->content = $this->product->getOriginal('content');
//                }
//                if (in_array('description', $this->product->copyable_to_childs)) {
//                    $childProduct->description = $this->product->getOriginal('description');
//                }
//                if (in_array('short_description', $this->product->copyable_to_childs)) {
//                    $childProduct->short_description = $this->product->getOriginal('short_description');
//                }
//                if (in_array('images', $this->product->copyable_to_childs)) {
//                    $childProduct->images = $this->product->getOriginal('images');
//                }
//                if (in_array('customBlocks', $this->product->copyable_to_childs)) {
//                    if ($childProduct->customBlocks) {
//                        $childProduct->customBlocks->delete();
//                    }
//                    $newCustomBlocks = $this->product->customBlocks->replicate();
//                    $newCustomBlocks->blockable_id = $childProduct->id;
//                    $newCustomBlocks->saveQuietly();
//                }
//
//                $childProduct->saveQuietly();
//            }
//        }

        $loop = 1;

        foreach ($this->productGroup->products as $product) {
            $product->productCategories()->sync($this->productGroup->productCategories);
            $product->calculateStock();
            $product->calculateTotalPurchases();
            $product->calculatePrices();
            if(($this->productGroup->only_show_parent_product && $loop == 1) || !$this->productGroup->only_show_parent_product){
                $product->indexable = 1;
            }else{
                $product->indexable = 0;
            }
            $product->site_ids = $this->productGroup->site_ids;
            $product->saveQuietly();

            if ($product->is_bundle) {
                $product->calculateDeliveryTime();
            }

            foreach (DB::table('dashed__product_bundle_products')->where('bundle_product_id', $product->id)->pluck('product_id') as $productId) {
                $bundleParentProduct = Product::find($productId);
                if ($bundleParentProduct) {
                    $bundleParentProduct->calculateStock();
                    $bundleParentProduct->calculateTotalPurchases();
                    $bundleParentProduct->calculateDeliveryTime();
                    $bundleParentProduct->calculatePrices();
                }
            }
            $loop++;
        }

        $this->productGroup->min_price = $this->productGroup->products->min('price');
        $this->productGroup->max_price = $this->productGroup->products->max('price');
        $this->productGroup->total_stock = $this->productGroup->products->sum('total_stock');
        $this->productGroup->total_purchases = $this->productGroup->products->sum('total_purchases');
        $this->productGroup->saveQuietly();

        ProductInformationUpdatedEvent::dispatch($this->productGroup);
    }
}
