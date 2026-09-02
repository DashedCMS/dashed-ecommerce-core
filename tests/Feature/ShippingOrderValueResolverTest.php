<?php

declare(strict_types=1);

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

/**
 * Een webshop kan de orderwaarde waartegen de min/max-orderwaarde van
 * verzendmethodes getoetst wordt bijsturen, bijvoorbeeld om een groot
 * product met een laag bedrag toch als "normale" bestelling te verzenden.
 */
beforeEach(function () {
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
    ShoppingCart::resolveShippingOrderValueUsing(null);
});

afterEach(fn () => ShoppingCart::resolveShippingOrderValueUsing(null));

$productVan = function (float $prijs): Product {
    $group = ProductGroup::create([
        'name' => ['nl' => 'Groep'],
        'slug' => ['nl' => 'groep-' . uniqid()],
        'short_description' => ['nl' => ''],
        'description' => ['nl' => ''],
        'content' => ['nl' => ''],
        'search_terms' => ['nl' => ''],
        'site_ids' => [Sites::getActive()],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'name' => ['nl' => 'Product'],
        'slug' => ['nl' => 'product-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 0,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => $prijs,
        'current_price' => $prijs,
        'public' => 1,
    ]));
};

$wagenMet = function (Product $product): void {
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default']);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'name' => 'Product',
        'unit_price' => $product->price,
        'quantity' => 1,
        'options' => [],
        'options_hash' => '',
    ]);

    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
};

$zoneMetMethodes = function (): void {
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Nederland'],
        'search_fields' => 'Nederland',
    ]);

    foreach ([['Klein', 0, 15], ['Normaal', 15, 100000]] as [$naam, $min, $max]) {
        ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'name' => ['nl' => $naam],
            'costs' => 5,
            'sort' => 'static_amount',
            'minimum_order_value' => $min,
            'maximum_order_value' => $max,
            'order' => 1,
        ]);
    }
};

$namen = fn () => collect(ShoppingCart::getAvailableShippingMethods('Nederland'))
    ->map(fn ($m) => $m->getTranslation('name', 'nl'))
    ->values()
    ->all();

it('toetst zonder resolver tegen de echte orderwaarde', function () use ($productVan, $wagenMet, $zoneMetMethodes, $namen) {
    $zoneMetMethodes();
    $wagenMet($productVan(8));

    expect($namen())->toBe(['Klein']);
});

it('toetst met resolver tegen de bijgestuurde orderwaarde', function () use ($productVan, $wagenMet, $zoneMetMethodes, $namen) {
    $zoneMetMethodes();
    $wagenMet($productVan(8));

    $ontvangen = [];
    ShoppingCart::resolveShippingOrderValueUsing(function (float $totaal, $cartItems) use (&$ontvangen): float {
        $ontvangen = ['totaal' => $totaal, 'aantal' => count($cartItems)];

        return max($totaal, 20.0);
    });

    // De wagen rekent hier met prijzen exclusief btw, dus het totaal ligt
    // iets boven de productprijs; het gaat erom dat de echte waarde binnenkomt.
    expect($namen())->toBe(['Normaal'])
        ->and($ontvangen['aantal'])->toBe(1)
        ->and($ontvangen['totaal'])->toBeGreaterThanOrEqual(8.0)->toBeLessThan(15.0);
});
