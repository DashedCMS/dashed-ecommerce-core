<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

/**
 * Regressie voor "Property [shippingMethods] does not exist on this collection
 * instance": in getAvailableShippingMethods geeft PaymentMethod::find() een
 * Collection terug wanneer het meegegeven paymentMethod een array is (bijv. een
 * request die paymentMethod als array stuurt), waarna ->shippingMethods crasht.
 */
function pmArrayResetCartStatics(): void
{
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
}

function pmArrayMakeCartWithProduct(): void
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

    $product = Product::withoutEvents(fn () => Product::create([
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

    $cookieName = config('dashed-ecommerce.cart_cookie', 'cart_token');
    $token = (string) Str::uuid();
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

    pmArrayResetCartStatics();
}

beforeEach(function () {
    pmArrayResetCartStatics();
});

it('geeft verzendmethodes terug wanneer paymentMethod als array binnenkomt', function () {
    pmArrayMakeCartWithProduct();

    $zone = ShippingZone::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Nederland'],
        'zones' => ['Nederland'],
        'search_fields' => 'Nederland',
    ]);
    ShippingMethod::create([
        'shipping_zone_id' => $zone->id,
        'name' => ['nl' => 'PostNL'],
        'costs' => 5,
        'sort' => 'static_amount',
        'minimum_order_value' => 0,
        'maximum_order_value' => 100000,
        'order' => 1,
    ]);

    $paymentMethod = PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'iDEAL'],
        'type' => 'online',
        'active' => 1,
        'psp' => 'mollie',
        'available_from_amount' => 0,
    ]);

    // paymentMethod komt als ARRAY binnen -> PaymentMethod::find() levert een
    // Collection. Voor de fix crasht dit op ->shippingMethods.
    $methods = ShoppingCart::getAvailableShippingMethods('Nederland', '', [$paymentMethod->id]);

    expect(collect($methods))->toHaveCount(1);
});
