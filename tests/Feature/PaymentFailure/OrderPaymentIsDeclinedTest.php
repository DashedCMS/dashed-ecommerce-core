<?php

use Dashed\DashedEcommerceCore\Models\OrderPayment;

it('is afgewezen als de Pay.nl-staat met DENIED begint', function () {
    expect((new OrderPayment(['attributes' => ['psp_state' => 'DENIED_63']]))->isDeclined())->toBeTrue()
        ->and((new OrderPayment(['attributes' => ['psp_state' => 'denied']]))->isDeclined())->toBeTrue();
});

it('is niet afgewezen bij CANCEL, PAID, een lege staat of zonder attributen', function () {
    expect((new OrderPayment(['attributes' => ['psp_state' => 'CANCEL']]))->isDeclined())->toBeFalse()
        ->and((new OrderPayment(['attributes' => ['psp_state' => 'PAID']]))->isDeclined())->toBeFalse()
        ->and((new OrderPayment(['attributes' => ['psp_state' => '']]))->isDeclined())->toBeFalse()
        ->and((new OrderPayment(['attributes' => ['terminal' => []]]))->isDeclined())->toBeFalse()
        ->and((new OrderPayment())->isDeclined())->toBeFalse();
});
