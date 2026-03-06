<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Models\Product;

class ProductsToEdit implements FromArray
{
    public function array(): array
    {
        $firstRow = [
            'Product ID (niet wijzigen)',
            'Product',
        ];

        foreach(ecommerce()->builder('productPriceFields') as $key => $priceField){
            $firstRow[] = $priceField['label'] ?? $key;
        }

        $firstRow = array_merge($firstRow, [
            'Voorraad',
            'EAN',
            'SKU',
            'BTW percentage',
            'Gewicht (in KG)',
            'Lengte (in CM)',
            'Breedte (in CM)',
            'Hoogte (in CM)',
        ]);

        $productsArray = [$firstRow];


        foreach (Product::orderBy('product_group_id')->get() as $product) {
            $productArray = [
                $product->id,
                $product->name,
            ];

            foreach(ecommerce()->builder('productPriceFields') as $key => $priceField){
                $productArray[] = $product->getRawOriginal($key) ?? '';
            }

            $productArray = array_merge($productArray, [
                $product->getRawOriginal('stock'),
                $product->getRawOriginal('ean'),
                $product->getRawOriginal('sku'),
                $product->getRawOriginal('vat_rate'),
                $product->getRawOriginal('weight'),
                $product->getRawOriginal('length'),
                $product->getRawOriginal('width'),
                $product->getRawOriginal('height'),
            ]);

            $productsArray[] = $productArray;
        }

        return [
            $productsArray,
        ];
    }
}
