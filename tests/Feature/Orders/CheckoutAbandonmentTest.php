<?php

use Dashed\DashedEcommerceCore\Models\CheckoutAbandonment;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('records a checkout abandonment with reason and context', function () {
    $row = CheckoutAbandonment::record('no_shipping_method', ['country' => 'BE'], cartId: 1, email: 'a@b.test', cartTotal: 25.0);

    expect($row)->not->toBeNull();
    expect($row->reason)->toBe('no_shipping_method');
    expect($row->context)->toBe(['country' => 'BE']);
    expect($row->cart_id)->toBe(1);
    expect((float) $row->cart_total)->toBe(25.0);
});

it('dedupes the same cart + reason within the dedupe window', function () {
    CheckoutAbandonment::record('no_payment_method', cartId: 7);
    $second = CheckoutAbandonment::record('no_payment_method', cartId: 7);

    expect($second)->toBeNull();
    expect(CheckoutAbandonment::where('cart_id', 7)->count())->toBe(1);
});

it('does not dedupe different reasons on the same cart', function () {
    CheckoutAbandonment::record('no_payment_method', cartId: 9);
    CheckoutAbandonment::record('no_shipping_method', cartId: 9);

    expect(CheckoutAbandonment::where('cart_id', 9)->count())->toBe(2);
});

it('always records when there is no cart id', function () {
    CheckoutAbandonment::record('no_items');
    CheckoutAbandonment::record('no_items');

    expect(CheckoutAbandonment::where('reason', 'no_items')->count())->toBe(2);
});
