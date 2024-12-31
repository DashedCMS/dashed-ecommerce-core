<?php

namespace Dashed\DashedEcommerceCore\Imports;

use App\Models\User;
use Dashed\DashedEcommerceCore\Models\Product;
use Maatwebsite\Excel\Concerns\FromArray;

class PricePerProductForUserImport implements FromArray
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
                'Product ID (niet wijzigen)',
                'Product',
                'Prijs',
                'Kortings prijs',
            ],
        ];

        foreach (Product::all() as $product) {
            $productsArray[] = [
                $product->id,
                $product->name,
                $product->priceForUser($this->user, false),
                $product->discountPriceForUser($this->user, false),
            ];
        }

        return [
            $productsArray,
        ];
    }
}
