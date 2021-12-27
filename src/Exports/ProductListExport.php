<?php

namespace Qubiqx\QcommerceEcommerceCore\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class ProductListExport implements FromArray
{
    protected $products;

    public function __construct($products)
    {
        $this->products = $products;
    }

    public function array(): array
    {
        $productsArray = [
            [
                'Naam',
                'Slug',
                'Prijs',
                'Oude prijs',
                'Gebruik voorraad',
                'Voorraad',
                'Voorraad status',
                'Doorverkoop bij uitverkocht',
                'Verwachte in voorraad datum',
                'Lage voorraad notificatie',
                'Lage voorraad notificatie limiet',
                'Aantal verkopen',
                'BTW percentage',
                'SKU',
                'EAN',
                'Openbaar',
                'Type product',
                'Start datum',
                'Eind datum',
                'Gewicht',
                'Lengte',
                'Breedte',
                'Hoogte',
            ]
        ];

        foreach ($this->products as $product) {
            $productsArray[] = [
                $product->name,
                $product->slug,
                $product->price,
                $product->new_price ?: '-',
                $product->use_stock ?: '-',
                $product->use_stock ? ($product->stock ?: '-') : '-',
                $product->use_stock ? '-' : $product->stock_status,
                $product->out_of_stock_sellable,
                $product->expected_in_stock_date ?: '-',
                $product->low_stock_notification,
                $product->low_stock_notification_limit,
                $product->purchases ?: '-',
                $product->vat_rate,
                $product->sku ?: '-',
                $product->ean ?: '-',
                $product->public ?: '-',
                $product->type,
                $product->start_date ?: '-',
                $product->end_date ?: '-',
                $product->weight ?: '-',
                $product->length ?: '-',
                $product->width ?: '-',
                $product->height ?: '-',
            ];
        }

        return [
            $productsArray
        ];
    }
}
