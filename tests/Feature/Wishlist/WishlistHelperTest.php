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

it('negeert een lijst van een andere gebruiker in de cookie', function () {
    $userA = \App\Models\User::create(['name' => 'Anna', 'email' => 'anna@example.com', 'password' => bcrypt('geheim')]);
    $userB = \App\Models\User::create(['name' => 'Bas', 'email' => 'bas@example.com', 'password' => bcrypt('geheim')]);
    $product = helperProduct();
    $lijstA = Wishlist::create(['user_id' => $userA->id]);
    $lijstA->items()->create(['product_id' => $product->id, 'price_at_add' => 20]);

    $this->actingAs($userB);
    request()->cookies->set('wishlist_token', $lijstA->token);

    $lijst = wishlistHelper()->getWishlist(create: true);

    expect($lijst->id)->not->toBe($lijstA->id)
        ->and($lijstA->refresh()->user_id)->toBe($userA->id)
        ->and($lijstA->items()->where('product_id', $product->id)->exists())->toBeTrue()
        ->and(collect(Cookie::getQueuedCookies())->map(fn ($c) => $c->getName())->all())->toContain('wishlist_token')
        ->and(collect(Cookie::getQueuedCookies())->firstWhere(fn ($c) => $c->getName() === 'wishlist_token')->getValue())->toBe($lijst->token)
        ->and($lijst->token)->not->toBe($lijstA->token);
});

it('memoiseert productIds() per verzoek en leest opnieuw na een wijziging', function () {
    $a = helperProduct();
    $b = helperProduct();
    wishlistHelper()->add($a);

    wishlistHelper()->productIds();

    \Illuminate\Support\Facades\DB::enableQueryLog();
    foreach (range(1, 5) as $poging) {
        wishlistHelper()->has($a);
    }
    expect(\Illuminate\Support\Facades\DB::getQueryLog())->toHaveCount(0);
    \Illuminate\Support\Facades\DB::disableQueryLog();

    wishlistHelper()->add($b);

    expect(wishlistHelper()->productIds())->toEqualCanonicalizing([$a->id, $b->id]);
});

it('een gebruiker zonder eigen lijst neemt de gastlijst over', function () {
    $userC = \App\Models\User::create(['name' => 'Chris', 'email' => 'chris@example.com', 'password' => bcrypt('geheim')]);
    $product = helperProduct();
    $gastLijst = Wishlist::create([]);
    $gastLijst->items()->create(['product_id' => $product->id, 'price_at_add' => 20]);

    $this->actingAs($userC);
    request()->cookies->set('wishlist_token', $gastLijst->token);

    $lijst = wishlistHelper()->getWishlist();

    expect($lijst->id)->toBe($gastLijst->id)
        ->and($lijst->user_id)->toBe($userC->id)
        ->and($lijst->email)->toBe($userC->email);
});
