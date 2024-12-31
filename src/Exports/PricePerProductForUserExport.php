<?php

namespace Dashed\DashedEcommerceCore\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Models\Product;

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
