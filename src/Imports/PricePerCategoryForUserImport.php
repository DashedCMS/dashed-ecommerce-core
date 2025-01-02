<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;

class PricePerCategoryForUserImport implements ToArray
{
    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function array(array $rows): void
    {
        unset($rows[0]);

        $productGroupIds = [];

        foreach ($rows as $row) {
            $productCategory = ProductCategory::find($row[0]);
            if (($row[2] || $row[3]) && $productCategory) {
                DB::table('dashed__product_category_user')->updateOrInsert(
                    [
                        'product_category_id' => $row[0],
                        'user_id' => $this->user->id,
                    ],
                    [
                        'discount_price' => $row[2],
                        'discount_percentage' => $row[3],
                    ]
                );

                foreach ($productCategory->products as $product) {
                    DB::table('dashed__product_user')->updateOrInsert(
                        [
                            'product_id' => $product->id,
                            'user_id' => $this->user->id,
                        ],
                        [
                            'activated_by_category' => true,
                            'discount_price' => $row[2],
                            'discount_percentage' => $row[3],
                        ]
                    );

                    $productGroupIds[] = $product->product_group_id;
                }
            } else {
                DB::table('dashed__product_category_user')
                    ->where('product_category_id', $row[0])
                    ->where('user_id', $this->user->id)
                    ->delete();

                DB::table('dashed__product_user')
                    ->whereIn('product_id', $productCategory->products->pluck('id'))
                    ->where('user_id', $this->user->id)
                    ->where('activated_by_category', true)
                    ->delete();
            }
        }

        foreach (ProductGroup::whereIn('id', $productGroupIds)->get() as $productGroup) {
            UpdateProductInformationJob::dispatch($productGroup, false);
        }
    }
}
