<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
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
        $affectedProductIds = [];

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

                    $affectedProductIds[] = $product->id;
                }
            } else {
                DB::table('dashed__product_category_price_group')
                    ->where('price_group_id', $this->priceGroupId)
                    ->where('product_category_id', $productCategory->id)
                    ->delete();

                // Alleen producten die deze prijsgroep daadwerkelijk via deze
                // categorie had, hoeven herberekend te worden (prijs valt dan
                // terug). Niet elk product van elke niet-geselecteerde categorie.
                $previouslyActivated = DB::table('dashed__product_price_group')
                    ->whereIn('product_id', $productCategory->products->pluck('id'))
                    ->where('price_group_id', $this->priceGroupId)
                    ->where('activated_by_category', true)
                    ->pluck('product_id')
                    ->all();

                DB::table('dashed__product_price_group')
                    ->whereIn('product_id', $productCategory->products->pluck('id'))
                    ->where('price_group_id', $this->priceGroupId)
                    ->where('activated_by_category', true)
                    ->delete();

                foreach ($previouslyActivated as $productId) {
                    $affectedProductIds[] = $productId;
                }
            }
        }

        $affectedProductIds = array_values(array_unique($affectedProductIds));
        if ($affectedProductIds) {
            RecalculateProductPricesJob::dispatch($affectedProductIds)->onQueue('ecommerce');
        }
    }
}
