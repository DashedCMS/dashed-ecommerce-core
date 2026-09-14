<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Mail\WishlistSavedMail;

it('noemt de producten en bevat de herstel-link', function () {
    $wishlist = Wishlist::create(['email' => 'jan@example.com']);
    // Product::create vuurt via het saved-event UpdateProductInformationJob af,
    // dat een productGroup verwacht; zonder groep crasht dat (zie
    // WishlistHelperTest). withoutEvents() onderdrukt dat.
    $product = Product::withoutEvents(fn () => Product::create([
        'name' => 'Zijden vaas',
        'slug' => 'zijden-vaas-'.Str::lower(Str::random(6)),
        'price' => 20,
        'current_price' => 20,
        'public' => 1,
        'site_ids' => ['default'],
    ]));
    $wishlist->items()->create(['product_id' => $product->id, 'price_at_add' => 20]);

    $html = (new WishlistSavedMail($wishlist))->render();

    expect($html)->toContain('Zijden vaas');

    // Crypt::encryptString() gebruikt een willekeurige IV, dus een tweede
    // aanroep van restoreUrl() levert nooit dezelfde ciphertext op als die in
    // de mail staat — een letterlijke stringvergelijking zou hier altijd
    // falen. In plaats daarvan halen we het token uit de link en ontcijferen
    // het, zodat we controleren dat de link daadwerkelijk naar deze lijst
    // terugvoert.
    expect($html)->toMatch('#/verlanglijst/herstel\?token=(?<token>[^"&]+)#');
    preg_match('#/verlanglijst/herstel\?token=(?<token>[^"&]+)#', $html, $matches);
    expect(Crypt::decryptString(urldecode($matches['token'])))->toBe($wishlist->token);
});
