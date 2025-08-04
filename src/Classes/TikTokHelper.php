<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Str;

class TikTokHelper
{
    public static function getShoppingCartItems($cartTotal = null): array
    {
        if(!$cartTotal){
            $cartTotal = ShoppingCart::total(false);
        }

        $items = [];

        foreach (ShoppingCart::cartItems() as $cartItem) {
            $items[] = [
                'content_id' => $cartItem->model->id,
                'content_type' => 'product',
                'content_name' => $cartItem->model->name,
                'content_category' => $cartItem->model->productCategories->first()?->name ?? null,
                'price' => number_format($cartItem->price, 2, '.', ''),
            ];

        }

        return [
            'contents' => $items,
            'currency' => 'EUR',
            'value' => number_format($cartTotal, 2, '.', ''),
        ];
    }
}
