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
                'Voorraad',
                'EAN',
                'BTW percentage',
                'Gewicht (in KG)',
                'Lengte (in CM)',
                'Breedte (in CM)',
                'Hoogte (in CM)',
            ],
        ];

        foreach (Product::all() as $product) {
            $productsArray[] = [
                $product->id,
                $product->name,
                $product->getRawOriginal('price'),
                $product->getRawOriginal('new_price'),
                $product->getRawOriginal('stock'),
                $product->getRawOriginal('ean'),
                $product->getRawOriginal('vat_rate'),
                $product->getRawOriginal('weight'),
                $product->getRawOriginal('length'),
                $product->getRawOriginal('width'),
                $product->getRawOriginal('height'),
            ];
        }

        return [
            $productsArray,
        ];
    }
}
