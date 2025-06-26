<?php

namespace Dashed\DashedEcommerceCore\Exports;

use App\Models\User;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class PricePerProductForUserExport implements FromArray
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function array(): array
    {
        $productsArray = [
            [
                'Product ID',
                'Product',
                'Kortings bedrag (aanpassen)',
                'Kortings percentage (aanpassen)',
            ],
        ];

        foreach (Product::all() as $product) {
            $product = DB::table('dashed__product_user')
                ->where('user_id', $this->user->id)
                ->where('product_id', $product->id)
                ->first();

            $productsArray[] = [
                $product->id,
                $product->name,
                $userProduct->discount_price ?? null,
                $userProduct->discount_percentage ?? null,
            ];
        }

        return [
            $productsArray,
        ];
    }
}
