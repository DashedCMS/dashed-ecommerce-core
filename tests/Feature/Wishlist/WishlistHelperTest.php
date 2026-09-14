<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Classes\WishlistHelper;

// Product::create vuurt via het saved-event UpdateProductInformationJob af, dat
// een productGroup verwacht; zonder groep crasht dat (zie WishlistModelTest).
// withoutEvents() onderdrukt dat, en current_price zetten we zelf gelijk aan
// price, anders leest add() straks altijd een lege prijs.
function helperProduct(float $price = 20): Product
{
    return Product::withoutEvents(fn () => Product::create([
        'name' => 'Vaas '.Str::random(4),
        'slug' => 'vaas-'.Str::lower(Str::random(8)),
        'price' => $price,
        'current_price' => $price,
        'public' => 1,
        'site_ids' => ['default'],
    ]));
}

beforeEach(function () {
    WishlistHelper::reset();
    request()->cookies->remove('wishlist_token');
});

it('maakt geen lijst aan bij alleen lezen', function () {
    expect(wishlistHelper()->getWishlist())->toBeNull()
        ->and(wishlistHelper()->count())->toBe(0)
        ->and(Wishlist::count())->toBe(0);
});

it('zet bij de eerste toevoeging een cookie en hergebruikt die in hetzelfde verzoek', function () {
    $a = helperProduct();
    $b = helperProduct();

    wishlistHelper()->add($a);
    wishlistHelper()->add($b);

    expect(Wishlist::count())->toBe(1)
        ->and(wishlistHelper()->productIds())->toEqualCanonicalizing([$a->id, $b->id])
        ->and(collect(Cookie::getQueuedCookies())->map(fn ($c) => $c->getName())->all())->toContain('wishlist_token');
});

it('toggelt en bewaart de prijs op het moment van toevoegen', function () {
    $product = helperProduct(17.5);

    expect(wishlistHelper()->toggle($product))->toBeTrue()
        ->and(wishlistHelper()->has($product))->toBeTrue()
        ->and(wishlistHelper()->getWishlist()->items()->first()->price_at_add)->toBe(17.5)
        ->and(wishlistHelper()->toggle($product))->toBeFalse()
        ->and(wishlistHelper()->count())->toBe(0);
});

it('voegt de gastlijst samen met de accountlijst bij inloggen en verwijdert de gastlijst', function () {
    $user = \App\Models\User::create(['name' => 'Jan', 'email' => 'jan@example.com', 'password' => bcrypt('geheim')]);
    $a = helperProduct();
    $b = helperProduct();
    $accountLijst = Wishlist::create(['user_id' => $user->id]);
    $accountLijst->items()->create(['product_id' => $a->id, 'price_at_add' => 20]);

    wishlistHelper()->add($a);
    wishlistHelper()->add($b);
    $gastToken = wishlistHelper()->getWishlist()->token;

    $this->actingAs($user);
    WishlistHelper::reset();
    request()->cookies->set('wishlist_token', $gastToken);

    $lijst = wishlistHelper()->getWishlist();

    expect($lijst->id)->toBe($accountLijst->id)
        ->and($lijst->items()->count())->toBe(2)
        ->and(Wishlist::where('token', $gastToken)->exists())->toBeFalse();
});

it('neemt een e-mailadres over maar overschrijft een bestaand adres niet', function () {
    wishlistHelper()->add(helperProduct());
    wishlistHelper()->adoptEmail('eerste@example.com');
    wishlistHelper()->adoptEmail('tweede@example.com');
    wishlistHelper()->adoptEmail(null);

    expect(wishlistHelper()->getWishlist()->email)->toBe('eerste@example.com');
});
