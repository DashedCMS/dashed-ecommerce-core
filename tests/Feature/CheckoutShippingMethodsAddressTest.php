<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;

function resetCartStatics(): void
{
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
}

function makeCheckoutProduct(): Product
{
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
        'name' => ['nl' => 'Test product'],
        'slug' => ['nl' => 'test-product-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 0,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => 10,
        'current_price' => 10,
        'public' => 1,
    ]));
}

function makeNlShippingZone(): ShippingZone
{
    return ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Nederland'],
        'search_fields' => 'Nederland',
    ]);
}

function makeShippingMethod(ShippingZone $zone, string $name, int $order): ShippingMethod
{
    return ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => $name],
        'costs' => 5,
        'sort' => 'static_amount',
        'minimum_order_value' => 0,
        'maximum_order_value' => 100000,
        'order' => $order,
    ]);
}

function startCheckoutCartWith(Product $product): void
{
    $cookieName = config('dashed-ecommerce.cart_cookie', 'cart_token');
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set($cookieName, $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default']);
    CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'name' => 'Test',
        'unit_price' => 10,
        'quantity' => 1,
        'options' => [],
        'options_hash' => '',
    ]);

    resetCartStatics();
}

beforeEach(function () {
    resetCartStatics();
});

it('recomputes shipping methods when the delivery address changes', function () {
    $product = makeCheckoutProduct();
    $zone = makeNlShippingZone();
    makeShippingMethod($zone, 'PostNL', 1);

    startCheckoutCartWith($product);

    $component = Livewire::test(Checkout::class);

    // Land staat standaard op Nederland; de eerste methode is beschikbaar.
    expect(collect($component->get('shippingMethods')))->toHaveCount(1);

    // Tweede methode wordt pas ná de eerste render toegevoegd.
    makeShippingMethod($zone, 'DHL', 2);

    // De klant vult zijn adres in. Dit moet de verzendmethodes verversen.
    $component->set('zipCode', '1234AB');

    expect(collect($component->get('shippingMethods')))->toHaveCount(2);
});
