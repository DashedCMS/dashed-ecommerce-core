<?php

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\CartAbandonedSource;

it('exposes items total locale and resume url', function () {
    $cart = Cart::create([
        'abandoned_email' => 'x@example.test',
        'locale' => 'nl',
        'token' => 'cart-token-123',
        'total' => 30.00,
    ]);
    $cart->items()->create(['quantity' => 2, 'unit_price' => 15.00, 'options_hash' => 'h']);

    $src = new CartAbandonedSource($cart->fresh());

    expect($src->email())->toBe('x@example.test')
        ->and($src->locale())->toBe('nl')
        ->and($src->total())->toBe(3000)
        ->and($src->items()->count())->toBe(1)
        ->and($src->resumeUrl())->toContain('/restore-cart')
        ->and($src->variables())->toBe([]);
});
