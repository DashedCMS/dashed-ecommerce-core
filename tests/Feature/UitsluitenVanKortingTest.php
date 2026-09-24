<?php

declare(strict_types=1);

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductGroupVolumeDiscount;

/**
 * Een product (of een hele productgroep) kan van korting worden uitgesloten:
 * kortingscodes, globale kortingen en staffelkorting slaan het over. Een
 * cadeaubon is betaalmiddel en blijft over de hele wagen bruikbaar.
 */
beforeEach(function () {
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
});

$groep = fn (bool $uitgesloten = false): ProductGroup => ProductGroup::create([
    'name' => ['nl' => 'Groep'],
    'slug' => ['nl' => 'groep-' . uniqid()],
    'short_description' => ['nl' => ''],
    'description' => ['nl' => ''],
    'content' => ['nl' => ''],
    'search_terms' => ['nl' => ''],
    'site_ids' => [Sites::getActive()],
    'exclude_from_discounts' => $uitgesloten,
]);

$product = function (float $prijs, bool $uitgesloten = false, ?ProductGroup $group = null) use ($groep): Product {
    $group ??= $groep();

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
        'exclude_from_discounts' => $uitgesloten,
    ]));
};

$code = fn (string $type, float $waarde, bool $cadeaubon = false, bool $globaal = false): DiscountCode => DiscountCode::withoutEvents(fn () => DiscountCode::create([
    'site_ids' => [Sites::getActive()],
    'name' => 'Korting',
    'code' => strtoupper(uniqid()),
    'type' => $type,
    'discount_percentage' => $type === 'percentage' ? $waarde : 0,
    'discount_amount' => $type === 'amount' ? $waarde : 0,
    'is_giftcard' => $cadeaubon,
    'is_global_discount' => $globaal,
    'valid_for' => 'all',
]));

/** @param array<int, array{0: Product, 1: int}> $regels */
$wagen = function (array $regels, ?DiscountCode $code = null): void {
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default', 'discount_code_id' => $code?->id]);

    foreach ($regels as [$product, $aantal]) {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'name' => 'Product',
            'unit_price' => $product->price,
            'quantity' => $aantal,
            'options' => [],
            'options_hash' => '',
        ]);
    }

    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
    cartHelper()->updateData();
};

it('geeft een procentuele code alleen op producten die niet zijn uitgesloten', function () use ($product, $code, $wagen) {
    $wagen([[$product(100), 1], [$product(10, uitgesloten: true), 1]], $code('percentage', 10));

    expect(cartHelper()->getDiscount())->toBe(10.0);
});

it('sluit via de productgroep alle producten van die groep uit', function () use ($groep, $product, $code, $wagen) {
    $outlet = $groep(uitgesloten: true);
    $vaas = $product(50, group: $outlet);

    expect($vaas->isExcludedFromDiscounts())->toBeTrue();

    $wagen([[$product(100), 1], [$vaas, 1]], $code('percentage', 10));

    expect(cartHelper()->getDiscount())->toBe(10.0);
});

it('laat een vast bedrag niet verder gaan dan wat er korting mag krijgen', function () use ($product, $code, $wagen) {
    $wagen([[$product(15), 1], [$product(50, uitgesloten: true), 1]], $code('amount', 20));

    expect(cartHelper()->getDiscount())->toBe(15.0);
});

it('laat een cadeaubon over de hele wagen gelden', function () use ($product, $code, $wagen) {
    $wagen([[$product(15), 1], [$product(50, uitgesloten: true), 1]], $code('amount', 20, cadeaubon: true));

    expect(cartHelper()->getDiscount())->toBe(20.0);
});

it('weigert een code als de wagen alleen uitgesloten producten bevat', function () use ($product, $code, $wagen) {
    $wagen([[$product(50, uitgesloten: true), 1]]);

    expect($code('percentage', 10)->isValidForCart())->toBeFalse()
        ->and($code('amount', 5)->isValidForCart())->toBeFalse()
        ->and($code('amount', 5, cadeaubon: true)->isValidForCart())->toBeTrue();
});

it('slaat globale kortingen en staffelkorting over', function () use ($groep, $product, $code) {
    $group = $groep();
    $gewoon = $product(10, group: $group);
    $uitgesloten = $product(10, uitgesloten: true, group: $group);

    $staffel = new ProductGroupVolumeDiscount();
    $staffel->product_group_id = $group->id;
    $staffel->type = 'percentage';
    $staffel->discount_percentage = 10;
    $staffel->min_quantity = 2;
    $staffel->active_for_all_variants = true;
    $staffel->save();

    $regel = fn (Product $p) => (object) ['product_id' => $p->id, 'quantity' => 2, 'options' => []];

    expect(Product::getShoppingCartItemPrice($regel($gewoon)))->toBe(18.0)
        ->and(Product::getShoppingCartItemPrice($regel($uitgesloten)))->toBe(20.0);

    $actie = $code('percentage', 20, globaal: true);

    expect($actie->isValidForProduct($gewoon))->toBeTrue()
        ->and($actie->isValidForProduct($uitgesloten))->toBeFalse();
});
