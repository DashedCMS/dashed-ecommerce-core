<?php

namespace Dashed\DashedEcommerceCore\Exports;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class PricePerCategoryForUserExport implements FromArray
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function array(): array
    {
        $productCategoriesArray = [
            [
                'Product Categorie ID',
                'Product Categorie',
                'Kortings bedrag (aanpassen)',
                'Kortings percentage (aanpassen)',
            ],
        ];

        foreach (ProductCategory::all() as $productCategory) {
            $userProductCategory = DB::table('dashed__product_category_user')
                ->where('user_id', $this->user->id)
                ->where('product_category_id', $productCategory->id)
                ->first();

            $productCategoriesArray[] = [
                $productCategory->id,
                $productCategory->name,
                $userProductCategory->discount_price ?? null,
                $userProductCategory->discount_percentage ?? null,
            ];
        }

        return [
            $productCategoriesArray,
        ];
    }
}
