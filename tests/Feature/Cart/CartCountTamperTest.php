<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\CartCount;

// Livewire laat een browser elke publieke eigenschap zetten via de `updates` in
// het verzoek. De cart-badge had `$cartCount` als publieke eigenschap en echode
// die rechtstreeks in de view, dus een array vanuit de client werd een TypeError
// in htmlspecialchars() (ruim duizend keer op demaanvis.nl), en een getal vanuit
// de client werd zonder meer getoond. Het aantal is afgeleide staat: het komt per
// render uit de winkelwagen en is niet vanuit het verzoek te zetten.

beforeEach(function () {
    CartHelper::$cart = null;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];
});

it('heeft geen publieke cartCount die de client kan zetten', function () {
    $publiek = collect((new ReflectionClass(CartCount::class))->getProperties(ReflectionProperty::IS_PUBLIC))
        ->map->getName();

    expect($publiek)->not->toContain('cartCount');
});

it('weigert een cartCount uit het verzoek in plaats van hem te renderen', function () {
    Livewire::test(CartCount::class)->set('cartCount', ['x' => 'y']);
})->throws(PublicPropertyNotFoundException::class);

it('toont het aantal uit de winkelwagen zelf', function () {
    $group = ProductGroup::create([
        'name' => ['en' => 'Badge'],
        'slug' => ['en' => 'badge-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => [Sites::getActive()],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'name' => ['en' => 'Badgeproduct'],
        'slug' => ['en' => 'badgeproduct-' . uniqid()],
        'site_ids' => [Sites::getActive()],
        'product_group_id' => $group->id,
        'use_stock' => 1,
        'stock' => 50,
        'total_stock' => 50,
        'in_stock' => 1,
        'stock_status' => 'in_stock',
        'price' => 10,
        'current_price' => 10,
        'public' => 1,
    ]));

    $token = (string) Str::uuid();
    $cookieName = config('dashed-ecommerce.cart_cookie', 'cart_token');
    $cart = Cart::create(['token' => $token, 'type' => 'default']);

    foreach ([1, 2] as $i) {
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'name' => 'Badgeproduct',
            'unit_price' => 10,
            'quantity' => 1,
            'options' => ['regel' => $i],
            'options_hash' => 'regel-' . $i,
        ]);
    }

    // De winkelwagen wordt op het cookie van het verzoek gevonden, en
    // Livewire::test() bouwt een eigen verzoek.
    Livewire::withCookie($cookieName, $token)
        ->test(CartCount::class)
        ->assertSeeHtml('class="cart-count"> 2 </span>')
        ->call('refreshCart')
        ->assertSeeHtml('class="cart-count"> 2 </span>');
});

it('blijft heel als de client cartType op een array zet', function () {
    Livewire::test(CartCount::class)
        ->set('cartType', ['default'])
        ->call('refreshCart')
        ->assertOk()
        ->assertSeeHtml('class="cart-count"> 0 </span>');
});
