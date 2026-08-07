<?php

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Cart;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * De kassa vuurt meerdere cart-verzoeken tegelijk af met hetzelfde
 * cookie-token. Zonder createOrFirst zagen twee gelijktijdige verzoeken
 * allebei geen winkelwagen en botste de tweede op de unieke index
 * dashed__carts_token_unique, met een 500 op retrieve-cart-for-customer.
 */
it('geeft de bestaande winkelwagen terug in plaats van te botsen op het token', function () {
    $user = User::factory()->create();
    $token = (string) Str::uuid();

    $attributes = ['type' => 'default', 'locale' => 'nl', 'currency' => 'EUR', 'user_id' => $user->id];

    $first = Cart::createOrFirst(['token' => $token], $attributes);

    // Het tweede, gelijktijdige verzoek: zelfde token, geen exception.
    $second = Cart::createOrFirst(['token' => $token], $attributes);

    expect($second->id)->toBe($first->id)
        ->and(Cart::where('token', $token)->count())->toBe(1);
});

it('laat een botsing op create wel klappen, zodat de fix aantoonbaar nodig is', function () {
    $token = (string) Str::uuid();
    Cart::create(['token' => $token, 'type' => 'default', 'locale' => 'nl', 'currency' => 'EUR']);

    expect(fn () => Cart::create(['token' => $token, 'type' => 'default', 'locale' => 'nl', 'currency' => 'EUR']))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('houdt het token uniek over meerdere winkelwagens', function () {
    $a = Cart::createOrFirst(['token' => (string) Str::uuid()], ['type' => 'default', 'locale' => 'nl', 'currency' => 'EUR']);
    $b = Cart::createOrFirst(['token' => (string) Str::uuid()], ['type' => 'default', 'locale' => 'nl', 'currency' => 'EUR']);

    expect($b->id)->not->toBe($a->id)
        ->and(Cart::count())->toBe(2);
});
