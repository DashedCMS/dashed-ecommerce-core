<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ProcessPricesPerPriceGroup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public function __construct(
        public int $priceGroupId,
        public array $data,
    ) {
    }

    public function handle(): void
    {
        $selectedCategoryIds = $this->data['product_category_ids'] ?? [];
        $productGroupIds = [];

        foreach (ProductCategory::with('products')->get() as $productCategory) {
            if (in_array($productCategory->id, $selectedCategoryIds)) {
                $price = $this->data[$productCategory->id . '_category_discount_price'] ?? null;
                $discountPercentage = $this->data[$productCategory->id . '_category_discount_percentage'] ?? null;

                DB::table('dashed__product_category_price_group')->updateOrInsert(
                    ['price_group_id' => $this->priceGroupId, 'product_category_id' => $productCategory->id],
                    ['discount_price' => $price, 'discount_percentage' => $discountPercentage]
                );

                foreach ($productCategory->products as $product) {
                    DB::table('dashed__product_price_group')->updateOrInsert(
                        ['price_group_id' => $this->priceGroupId, 'product_id' => $product->id],
                        [
                            'discount_price' => $price,
                            'discount_percentage' => $discountPercentage,
                            'activated_by_category' => true,
                        ]
                    );

                    $productGroupIds[] = $product->product_group_id;
                }
            } else {
                DB::table('dashed__product_category_price_group')
                    ->where('price_group_id', $this->priceGroupId)
                    ->where('product_category_id', $productCategory->id)
                    ->delete();

                DB::table('dashed__product_price_group')
                    ->whereIn('product_id', $productCategory->products->pluck('id'))
                    ->where('price_group_id', $this->priceGroupId)
                    ->where('activated_by_category', true)
                    ->delete();
            }
        }

        foreach (ProductGroup::whereIn('id', array_unique($productGroupIds))->get() as $productGroup) {
            UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
        }
    }
}
