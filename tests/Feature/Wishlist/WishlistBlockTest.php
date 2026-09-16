<?php

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Mail\EmailBlocks\WishlistBlock;

// Product::create vuurt via het saved-event UpdateProductInformationJob af, dat
// een productGroup verwacht; zonder groep crasht dat (zie WishlistModelTest).
// withoutEvents() onderdrukt dat, dus current_price zetten we hier zelf, gelijk
// aan price — anders leest WishlistItem::priceDropped() altijd null.
function blockProduct(string $name): Product
{
    return Product::withoutEvents(fn () => Product::create([
        'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        'price' => 20,
        'current_price' => 20,
        'public' => 1,
        'site_ids' => ['default'],
    ]));
}

it('rendert per ontvanger en toont diens producten met een knop naar de lijst', function () {
    $wishlist = Wishlist::create(['email' => 'jan@example.com']);
    $wishlist->items()->create(['product_id' => blockProduct('Blauwe vaas')->id, 'price_at_add' => 25]);
    Wishlist::create(['email' => 'piet@example.com'])->items()->create(['product_id' => blockProduct('Rode vaas')->id, 'price_at_add' => 20]);

    expect(WishlistBlock::perRecipient())->toBeTrue();

    $html = WishlistBlock::renderForRecipient(['limit' => 4, 'columns' => 2, 'empty' => 'verbergen'], ['siteUrl' => 'https://shop.test', 'recipientEmail' => 'jan@example.com', 'subscriber' => null]);

    expect($html)->toContain('Blauwe vaas')->not->toContain('Rode vaas')->toContain('verlanglijst/herstel')->toContain('Prijs gedaald');
});

it('rendert niets of bestsellers bij een lege lijst', function () {
    blockProduct('Bestseller');
    $context = ['siteUrl' => 'https://shop.test', 'recipientEmail' => 'niemand@example.com', 'subscriber' => null];

    expect(WishlistBlock::renderForRecipient(['empty' => 'verbergen'], $context))->toBe('')
        ->and(WishlistBlock::renderForRecipient(['empty' => 'bestsellers', 'limit' => 4], $context))->toContain('Bestseller');
});

it('rendert in het sjabloon (zonder ontvanger) niets', function () {
    expect(WishlistBlock::render([], ['siteUrl' => 'https://shop.test']))->toBe('');
});
