<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Classes\CartHelper;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

/**
 * hardevine/shoppingcart is weg (geen versie voor Laravel 13, niet meer
 * onderhouden). De laatste plekken die nog op de Gloudemans-sessie leunden
 * lopen nu over de DB-cart; dit bewaakt dat ze hetzelfde zeggen als de DB.
 */
// CartHelper bewaart de winkelwagen statisch; na de rollback van de vorige
// test wijst die naar een rij die niet meer bestaat.
beforeEach(function () {
    // Een verwijderde regel laat UpdateProductInformationJob draaien, en die
    // gebruikt GREATEST(), wat SQLite niet kent.
    Queue::fake();

    CartHelper::$cart = null;
    CartHelper::$initialized = false;
    CartHelper::$cartItemsInitialized = false;
    CartHelper::$cartItems = [];
    CartHelper::$cartProductsById = [];

    // Zonder cookie op het verzoek maakt elke tokenopvraag een nieuwe winkelwagen.
    request()->cookies->set(config('dashed-ecommerce.cart_cookie', 'cart_token'), (string) Str::uuid());
});

function dbCartTestProduct(): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'P'],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['default'],
        'current_price' => 10.00,
        'price' => 10.00,
        'vat_rate' => 21,
    ]));
}

it('telt de aantallen op over de DB-cart', function () {
    $product = dbCartTestProduct();

    expect(ShoppingCart::cartItemsCount())->toBe(0);

    cartHelper()->addToCart($product->id, 2);
    expect(ShoppingCart::cartItemsCount())->toBe(2);

    cartHelper()->addToCart($product->id, 3, ['options' => ['kleur' => 'rood']]);
    expect(ShoppingCart::cartItemsCount())->toBe(5);
});

it('vindt een regel op rowId en niet meer na verwijderen', function () {
    $product = dbCartTestProduct();
    cartHelper()->addToCart($product->id, 1);

    $rowId = (string) collect(cartHelper()->getCartItems())->first()->rowId;

    expect(ShoppingCart::hasCartitemByRowId($rowId))->toBeTrue()
        ->and(ShoppingCart::hasCartitemByRowId('999999'))->toBeFalse();

    cartHelper()->removeItem($rowId);

    expect(ShoppingCart::hasCartitemByRowId($rowId))->toBeFalse();
});

it('zet een vrij product zonder Product-model in de DB-cart en laat het staan bij opschonen', function () {
    cartHelper()->addCustomProduct('Maatwerk', 2, 12.50, ['vat_rate' => 21]);

    $item = CartItem::first();

    expect($item->product_id)->toBeNull()
        ->and($item->isCustom())->toBeTrue()
        ->and($item->name)->toBe('Maatwerk')
        ->and($item->quantity)->toBe(2)
        ->and(Product::getShoppingCartItemPrice(collect(cartHelper()->getCartItems())->first()))->toBe(25.0)
        ->and(ShoppingCart::cartItemsCount())->toBe(2);

    cartHelper()->removeInvalidItems(false);

    expect(CartItem::count())->toBe(1);
});
