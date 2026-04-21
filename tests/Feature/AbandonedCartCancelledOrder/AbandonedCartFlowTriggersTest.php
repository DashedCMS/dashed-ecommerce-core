<?php

use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;

it('casts triggers column to array', function () {
    $flow = AbandonedCartFlow::create([
        'name' => 'Test',
        'is_active' => true,
        'triggers' => ['cart_with_email', 'cancelled_order'],
    ]);

    expect($flow->fresh()->triggers)->toBe(['cart_with_email', 'cancelled_order']);
});

it('hasTrigger returns true when trigger is in list', function () {
    $flow = new AbandonedCartFlow(['triggers' => ['cancelled_order']]);

    expect($flow->hasTrigger('cancelled_order'))->toBeTrue()
        ->and($flow->hasTrigger('cart_with_email'))->toBeFalse();
});

it('hasTrigger returns false when triggers is null', function () {
    $flow = new AbandonedCartFlow(['triggers' => null]);

    expect($flow->hasTrigger('cancelled_order'))->toBeFalse();
});
