<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Models\WishlistItem;

// Product::create vuurt via het saved-event UpdateProductInformationJob af, dat
// een productGroup verwacht; zonder groep crasht dat. withoutEvents() (zoals in
// tests/Feature/Cart/CartCountTamperTest.php) onderdrukt dat voor deze losse
// testproducten, die alleen als koppeling voor de verlanglijst dienen. Diezelfde
// job berekent normaal ook current_price uit price, dus die zetten we hier zelf
// (gelijk aan price, tenzij expliciet meegegeven) — anders leest priceDropped()
// altijd null.
function wishlistProduct(array $extra = []): Product
{
    return Product::withoutEvents(fn () => Product::create(array_merge([
        'name' => 'Vaas '.Str::random(4),
        'slug' => 'vaas-'.Str::lower(Str::random(8)),
        'price' => 20,
        'current_price' => $extra['price'] ?? 20,
        'public' => 1,
        'site_ids' => ['default'],
    ], $extra)));
}

it('heeft de tabellen met de verwachte kolommen', function () {
    expect(Schema::hasColumns('dashed__wishlists', ['token', 'user_id', 'email', 'share_token', 'locale', 'site_id', 'last_activity_at', 'flow_cooldown_until']))->toBeTrue()
        ->and(Schema::hasColumns('dashed__wishlist_items', ['wishlist_id', 'product_id', 'price_at_add']))->toBeTrue()
        ->and(Schema::hasColumn('dashed__abandoned_cart_emails', 'wishlist_id'))->toBeTrue();
});

it('krijgt bij aanmaken een uuid-token en staat een product maar één keer toe', function () {
    $wishlist = Wishlist::create([]);
    $product = wishlistProduct();

    expect(Str::isUuid($wishlist->token))->toBeTrue();

    WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $product->id, 'price_at_add' => 20]);

    expect(fn () => WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $product->id, 'price_at_add' => 20]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('vindt een lijst op e-mail of op de gebruiker met dat e-mailadres', function () {
    $user = \App\Models\User::create(['name' => 'Jan', 'email' => 'jan@example.com', 'password' => bcrypt('geheim')]);
    $opEmail = Wishlist::create(['email' => 'piet@example.com']);
    $opAccount = Wishlist::create(['user_id' => $user->id]);
    Wishlist::create(['email' => 'anders@example.com']);

    expect(Wishlist::forEmail('piet@example.com')->pluck('id')->all())->toBe([$opEmail->id])
        ->and(Wishlist::forEmail('jan@example.com')->pluck('id')->all())->toBe([$opAccount->id]);
});

it('toont alleen publieke producten en weet of de prijs gedaald is', function () {
    $wishlist = Wishlist::create([]);
    $publiek = wishlistProduct(['price' => 15]);
    $verborgen = wishlistProduct(['public' => 0]);
    $item = WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $publiek->id, 'price_at_add' => 20]);
    WishlistItem::create(['wishlist_id' => $wishlist->id, 'product_id' => $verborgen->id, 'price_at_add' => 20]);

    expect($wishlist->publicItems()->pluck('product_id')->all())->toBe([$publiek->id])
        ->and($item->priceDropped())->toBeTrue();
});
