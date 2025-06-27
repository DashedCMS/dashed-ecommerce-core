<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

class PricePerProductForUserImport implements ToArray
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
            $product = Product::find($row[0]);
            if ((($row[2] ?? false) || ($row[3] ?? false)) && $product) {
                DB::table('dashed__product_user')->updateOrInsert(
                    [
                        'product_id' => $row[0],
                        'user_id' => $this->user->id,
                    ],
                    [
                        'discount_price' => $row[2],
                        'discount_percentage' => $row[3],
                    ]
                );

                $productGroupIds[] = $product->product_group_id;
            } else {
                DB::table('dashed__product_user')
                    ->where('product_id', $row[0])
                    ->where('user_id', $this->user->id)
                    ->delete();
            }
        }

        foreach (ProductGroup::whereIn('id', $productGroupIds)->get() as $productGroup) {
            UpdateProductInformationJob::dispatch($productGroup, false)->onQueue('ecommerce');
        }
    }
}
