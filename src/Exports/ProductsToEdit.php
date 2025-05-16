<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Models\Product;

class ProductsToEdit implements FromArray
{
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
                $product->getRawOriginal('current_price'),
                $product->getRawOriginal('discount_price'),
                $product->getRawOriginal('ean'),
                $product->getRawOriginal('vat_rate'),
            ];
        }

        return [
            $productsArray,
        ];
    }
}
