<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Classes\WishlistHelper;
use Dashed\DashedEcommerceCore\Controllers\Frontend\WishlistController;

beforeEach(fn () => WishlistHelper::reset());

it('zet met de herstel-link de cookie op die lijst en stuurt door', function () {
    $wishlist = Wishlist::create(['email' => 'jan@example.com']);
    $wishlist->items()->create(['product_id' => 1, 'price_at_add' => 10]);

    $response = $this->get(WishlistController::restoreUrl($wishlist));

    $response->assertRedirect();
    // De cookie komt versleuteld terug (EncryptCookies-middleware); vergelijk
    // daarom de ontsleutelde waarde in plaats van assertCookie(..., false),
    // dat de rauwe (nog versleutelde) waarde zou vergelijken.
    $response->assertCookie('wishlist_token');
    expect($response->getCookie('wishlist_token')->getValue())->toBe($wishlist->token);
});

it('stuurt bij een ongeldig hersteltoken naar de homepage zonder cookie', function () {
    $response = $this->get(route('dashed.frontend.wishlist.restore', ['token' => 'onzin']));

    $response->assertRedirect('/');
    // De 'web'-middlewaregroep zet altijd een sessie- en XSRF-cookie; alleen
    // de wishlist-cookie zelf mag hier ontbreken.
    $response->assertCookieMissing('wishlist_token');
});

it('voegt de gastlijst samen als een ingelogde gebruiker een herstel-link opent', function () {
    $user = \App\Models\User::create(['name' => 'Jan', 'email' => 'jan@example.com', 'password' => bcrypt('geheim')]);
    $eigen = Wishlist::create(['user_id' => $user->id]);
    $eigen->items()->create(['product_id' => 1, 'price_at_add' => 10]);
    $gast = Wishlist::create([]);
    $gast->items()->create(['product_id' => 2, 'price_at_add' => 10]);

    $this->actingAs($user)->get(WishlistController::restoreUrl($gast))->assertRedirect();

    expect($eigen->items()->count())->toBe(2)
        ->and(Wishlist::find($gast->id))->toBeNull();
});

it('geeft 404 op een onbekend deeltoken', function () {
    $this->get(route('dashed.frontend.wishlist.shared', ['shareToken' => (string) Str::uuid()]))->assertNotFound();
});
