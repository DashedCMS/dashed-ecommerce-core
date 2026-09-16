<?php

declare(strict_types=1);

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

/**
 * De minimale/maximale orderwaarde van een verzendmethode (bijv. gratis
 * verzending vanaf € 100) wordt getoetst op de orderwaarde ná
 * korting; cadeaubonnen tellen niet mee. Een korting groter dan de wagen
 * mag nooit alle verzendmethodes laten verdwijnen.
 */
beforeEach(function () {
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
    ShoppingCart::resolveShippingOrderValueUsing(null);
});

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

$wagenMet = function (Product $product, ?DiscountCode $code = null): void {
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default', 'discount_code_id' => $code?->id]);
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
    cartHelper()->updateData();
};

$kortingVan = function (float $bedrag, bool $cadeaubon = false): DiscountCode {
    return DiscountCode::withoutEvents(fn () => DiscountCode::create([
        'site_ids' => [Sites::getActive()],
        'name' => $cadeaubon ? 'Cadeaubon' : 'Korting',
        'code' => strtoupper(uniqid()),
        'type' => 'amount',
        'discount_amount' => $bedrag,
        'is_giftcard' => $cadeaubon,
    ]));
};

$zoneMetDrempel = function (): void {
    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Nederland'],
        'search_fields' => 'Nederland',
    ]);

    foreach ([['Betaald', 'static_amount', 4.95, 0, 100], ['Gratis', 'free_delivery', 0, 100, 100000]] as [$naam, $sort, $kosten, $min, $max]) {
        ShippingMethod::create([
            'shipping_zone_id' => $zone->id,
            'name' => ['nl' => $naam],
            'costs' => $kosten,
            'sort' => $sort,
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

it('toetst op de orderwaarde ná korting', function () use ($productVan, $wagenMet, $kortingVan, $zoneMetDrempel, $namen) {
    $zoneMetDrempel();
    $wagenMet($productVan(200), $kortingVan(150));

    expect($namen())->toBe(['Betaald']);
});

it('laat een cadeaubon de drempel nooit verlagen', function () use ($productVan, $wagenMet, $kortingVan, $zoneMetDrempel, $namen) {
    $zoneMetDrempel();
    $wagenMet($productVan(200), $kortingVan(150, cadeaubon: true));

    expect($namen())->toBe(['Gratis']);
});

it('houdt verzendmethodes over als de korting groter is dan de wagen', function () use ($productVan, $wagenMet, $kortingVan, $zoneMetDrempel, $namen) {
    $zoneMetDrempel();
    $wagenMet($productVan(200), $kortingVan(1000));

    expect($namen())->toBe(['Betaald']);
});

it('verandert niets zonder kortingscode', function () use ($productVan, $wagenMet, $zoneMetDrempel, $namen) {
    $zoneMetDrempel();
    $wagenMet($productVan(200));

    expect($namen())->toBe(['Gratis']);
});
