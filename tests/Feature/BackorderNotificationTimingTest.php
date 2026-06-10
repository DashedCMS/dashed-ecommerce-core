<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Classes\Sites;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\Cart as CartComponent;

function makeBackorderCartProduct(): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Group ' . uniqid()],
        'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => [Sites::getActive()],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => 'Vaas'],
        'slug' => ['en' => 'vaas-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 1,
        'out_of_stock_sellable' => 1,
        'stock' => 1,
        // Hoog total_stock zodat removeInvalidItems het item niet terugbrengt
        // (zoals calculateStock dat doet voor out_of_stock_sellable producten).
        'total_stock' => 100000,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'expected_delivery_in_days' => 5,
        'price' => 10,
        'current_price' => 10,
        'public' => 1,
    ]));
}

function seedBackorderCart(Product $product, int $quantity): CartItem
{
    $cookieName = config('dashed-ecommerce.cart_cookie', 'cart_token');
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set($cookieName, $token);

    $cart = Cart::create(['token' => $token, 'type' => 'default']);

    $item = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'name' => 'Vaas',
        'unit_price' => 10,
        'quantity' => $quantity,
        'options' => [],
        'options_hash' => '',
    ]);

    // Bind the seeded cart into CartHelper's static state so it resolves
    // deterministically (getOrCreateCart short-circuits on static::$cart),
    // without depending on the cart cookie propagating into Livewire::test.
    CartHelper::$cart = $cart;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];

    return $item;
}

beforeEach(function () {
    Cache::forget('free-shipping-method');
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
});

it('does not fire the backorder notification on mount (page load)', function () {
    $product = makeBackorderCartProduct();
    seedBackorderCart($product, 2);

    // Guard: the cart genuinely contains a backorder, so a passing assertNotNotified
    // below means "mount stayed silent", not "there was nothing to notify about".
    expect(cartHelper()->getBackorderNotices())->not->toBeEmpty();

    Livewire::test(CartComponent::class)
        ->assertNotNotified(Translation::get('cart-backorder-title', 'cart', 'Niet alle producten zijn volledig op voorraad'));
});

it('notifyBackorders sends the warning for a backorder cart', function () {
    // notifyBackorders() is the method the add-to-cart and change-quantity
    // mutations invoke (and which checkCart deliberately no longer calls). It
    // sends the Filament warning, which is flashed to the session and read back
    // by assertNotified. This is also the positive control proving the mount
    // test above ("stays silent") is meaningful and not vacuous.
    $product = makeBackorderCartProduct();
    seedBackorderCart($product, 2);

    (new CartComponent())->notifyBackorders();

    Notification::assertNotified(Translation::get('cart-backorder-title', 'cart', 'Niet alle producten zijn volledig op voorraad'));
});
