<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartSuggestions;

function makeLivewireProductGroup(): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => 'LiveGroup'],
        'slug' => ['en' => 'live-group-'.uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
    ]);
}

function makeLivewireProduct(string $name, float $price, ProductGroup $group): Product
{
    return Product::withoutEvents(function () use ($name, $price, $group) {
        return Product::create([
            'name' => ['en' => $name],
            'slug' => ['en' => \Illuminate\Support\Str::slug($name).'-'.uniqid()],
            'site_ids' => [\Dashed\DashedCore\Classes\Sites::getActive()],
            'product_group_id' => $group->id,
            'use_stock' => 1,
            'stock' => 50,
            'total_stock' => 50,
            'in_stock' => 1,
            'stock_status' => 'in_stock',
            'price' => $price,
            'current_price' => $price,
            'public' => 1,
        ]);
    });
}

function setupCartWithItems(array $productIds): void
{
    $cookieName = config('dashed-ecommerce.cart_cookie', 'cart_token');
    $token = (string) \Illuminate\Support\Str::uuid();
    request()->cookies->set($cookieName, $token);

    $cart = Cart::create([
        'token' => $token,
        'type' => 'default',
    ]);

    foreach ($productIds as $pid) {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $pid,
            'name' => 'Test',
            'unit_price' => 10,
            'quantity' => 1,
            'options' => [],
            'options_hash' => '',
        ]);
    }

    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cart = null;
    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cartItemsInitialized = false;
    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cartItems = [];
    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cartProductsById = [];
}

beforeEach(function () {
    Cache::forget('free-shipping-method');
    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cart = null;
    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cartItemsInitialized = false;
    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cartItems = [];
    \Dashed\DashedEcommerceCore\Classes\CartHelper::$cartProductsById = [];
});

it('renders nothing when cart_suggestions_enabled is false', function () {
    Customsetting::set('cart_suggestions_enabled', '0');

    Livewire::test(CartSuggestions::class, ['view' => 'cart'])
        ->assertCount('suggestions', 0);
});

it('mounts with default cart view', function () {
    Customsetting::set('cart_suggestions_enabled', '0');

    Livewire::test(CartSuggestions::class)
        ->assertSet('view', 'cart');
});

it('mounts with checkout view', function () {
    Customsetting::set('cart_suggestions_enabled', '0');

    Livewire::test(CartSuggestions::class, ['view' => 'checkout'])
        ->assertSet('view', 'checkout');
});

it('mounts with popup view', function () {
    Customsetting::set('cart_suggestions_enabled', '0');

    Livewire::test(CartSuggestions::class, ['view' => 'popup'])
        ->assertSet('view', 'popup');
});

it('falls back to cart view when given invalid view', function () {
    Customsetting::set('cart_suggestions_enabled', '0');

    Livewire::test(CartSuggestions::class, ['view' => 'invalid'])
        ->assertSet('view', 'cart');
});

it('addToCart calls cartHelper and dispatches refreshCart', function () {
    Customsetting::set('cart_suggestions_enabled', '0');

    $group = makeLivewireProductGroup();
    $product = makeLivewireProduct('Add', 10, $group);

    setupCartWithItems([]);

    Livewire::test(CartSuggestions::class, ['view' => 'cart'])
        ->call('addToCart', $product->id)
        ->assertDispatched('refreshCart')
        ->assertDispatched('productAddedToCart');
});
