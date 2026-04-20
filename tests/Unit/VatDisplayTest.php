<?php

use Dashed\DashedEcommerceCore\Classes\VatDisplay;

it('converts an inclusive amount to its exclusive value using the given vat rate', function () {
    expect(VatDisplay::exFromIncl(121.00, 21))->toEqualWithDelta(100.00, 0.001);
    expect(VatDisplay::exFromIncl(109.00, 9))->toEqualWithDelta(100.00, 0.001);
    expect(VatDisplay::exFromIncl(100.00, 0))->toEqualWithDelta(100.00, 0.001);
});

it('returns zero when the incl amount is zero', function () {
    expect(VatDisplay::exFromIncl(0.0, 21))->toEqualWithDelta(0.0, 0.001);
});

it('treats null/negative vat rate as 0%', function () {
    expect(VatDisplay::exFromIncl(50.0, null))->toEqualWithDelta(50.0, 0.001);
    expect(VatDisplay::exFromIncl(50.0, -5))->toEqualWithDelta(50.0, 0.001);
});

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Cart;

it('defaults to incl when no inputs are given', function () {
    expect(VatDisplay::resolveMode(null, null))->toBe('incl');
});

it('respects a cart flag over a user flag', function () {
    $cart = new Cart(['prices_ex_vat' => true]);
    $user = new User();
    $user->show_prices_ex_vat = false;

    expect(VatDisplay::resolveMode($cart, $user))->toBe('ex');
});

it('falls back to the user flag when the cart has no flag set', function () {
    $user = new User();
    $user->show_prices_ex_vat = true;

    expect(VatDisplay::resolveMode(null, $user))->toBe('ex');
});

it('ignores a user without the flag', function () {
    $user = new User();
    $user->show_prices_ex_vat = false;

    expect(VatDisplay::resolveMode(null, $user))->toBe('incl');
});
