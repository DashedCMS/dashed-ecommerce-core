<?php

use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\CartAbandonedSource;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\AbandonedCartTriggers;
use Dashed\DashedEcommerceCore\Services\AbandonedCart\AbandonedCartSourceResolver;

it('kent de twee bestaande triggers met hun labels', function () {
    expect(AbandonedCartTriggers::labels())->toHaveKeys(['cart_with_email', 'cancelled_order'])
        ->and(AbandonedCartTriggers::has('cart_with_email'))->toBeTrue()
        ->and(AbandonedCartTriggers::has('onbekend'))->toBeFalse();
});

it('lost een wagen-trigger op naar dezelfde bron als voorheen', function () {
    $cart = Cart::create(['abandoned_email' => 'jan@example.com']);
    $record = AbandonedCartEmail::create(['cart_id' => $cart->id, 'trigger_type' => 'cart_with_email', 'email' => 'jan@example.com', 'email_number' => 1, 'send_at' => now()]);

    expect(AbandonedCartSourceResolver::for($record))->toBeInstanceOf(CartAbandonedSource::class)
        ->and($record->source()?->id)->toBe($cart->id);
});

it('gooit bij een onbekende trigger', function () {
    $record = new AbandonedCartEmail(['trigger_type' => 'bestaat_niet']);

    expect(fn () => AbandonedCartTriggers::resolve($record))->toThrow(InvalidArgumentException::class);
});

it('laat een geregistreerde extra trigger door het register lopen', function () {
    AbandonedCartTriggers::register('test_trigger', 'Test', 'Alleen voor deze test', fn () => null);

    expect(AbandonedCartTriggers::labels())->toHaveKey('test_trigger');
    expect(fn () => AbandonedCartTriggers::resolve(new AbandonedCartEmail(['trigger_type' => 'test_trigger'])))
        ->toThrow(InvalidArgumentException::class, 'source missing');
});
