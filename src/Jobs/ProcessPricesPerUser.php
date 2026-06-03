<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ProcessPricesPerUser implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;
    public array $data;
    public User $user;

    public function __construct(User $user, array $data)
    {
        $this->user = $user;
        $this->data = $data;
    }

    public function handle(): void
    {
        $data = $this->data;
        $user = $this->user;

        // Zit de gebruiker in een prijsgroep, dan is die leidend: persoonlijke
        // prijzen gelden niet meer en worden opgeschoond zodat ze de groepsprijs
        // niet schaduwen (zie Product::priceForUser).
        if (! empty($data['price_group_id'])) {
            $affectedProductIds = DB::table('dashed__product_user')
                ->where('user_id', $user->id)
                ->pluck('product_id')
                ->all();

            DB::table('dashed__product_user')->where('user_id', $user->id)->delete();
            DB::table('dashed__product_category_user')->where('user_id', $user->id)->delete();

            $affectedProductIds = array_values(array_unique($affectedProductIds));
            if ($affectedProductIds) {
                RecalculateProductPricesJob::dispatch($affectedProductIds)->onQueue('ecommerce');
            }

            return;
        }

        // Bij een gekozen prijsgroep zijn de categorie-velden verborgen, dus
        // de formulierdata kan 'product_category_ids' missen.
        $selectedCategoryIds = $data['product_category_ids'] ?? [];

        $affectedProductIds = [];

        foreach (ProductCategory::with('products')->get() as $productCategory) {
            if (in_array($productCategory->id, $selectedCategoryIds)) {
                $price = $data[$productCategory->id . '_category_discount_price'] ?? null;
                $discountPercentage = $data[$productCategory->id . '_category_discount_percentage'] ?? null;

                DB::table('dashed__product_category_user')
                    ->updateOrInsert(
                        ['product_category_id' => $productCategory->id, 'user_id' => $user->id],
                        ['discount_price' => $price, 'discount_percentage' => $discountPercentage]
                    );

                foreach ($productCategory->products as $product) {
                    DB::table('dashed__product_user')->updateOrInsert(
                        [
                            'product_id' => $product->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'discount_price' => $price,
                            'discount_percentage' => $discountPercentage,
                        ]
                    );

                    $affectedProductIds[] = $product->id;
                }
            } else {
                DB::table('dashed__product_category_user')
                    ->where('product_category_id', $productCategory->id)
                    ->where('user_id', $user->id)
                    ->delete();

                // Alleen producten die deze gebruiker daadwerkelijk via deze
                // categorie had, hoeven herberekend te worden (prijs valt terug).
                $previouslyActivated = DB::table('dashed__product_user')
                    ->whereIn('product_id', $productCategory->products->pluck('id'))
                    ->where('user_id', $user->id)
                    ->where('activated_by_category', true)
                    ->pluck('product_id')
                    ->all();

                DB::table('dashed__product_user')
                    ->whereIn('product_id', $productCategory->products->pluck('id'))
                    ->where('user_id', $user->id)
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
