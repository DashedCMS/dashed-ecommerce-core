<?php

use Dashed\DashedMobileApi\MobileApiRegistry;

it('registers the seven sequenceable fulfil steps, hidden from the per-order list', function () {
    $registry = app(MobileApiRegistry::class);
    $byKey = collect($registry->orderActions())->keyBy('key');

    foreach (['mark_packed','create_label','print_label','print_packing_slip','print_invoice','set_fulfillment_status','mark_paid'] as $key) {
        expect($byKey->has($key))->toBeTrue("ontbreekt: {$key}")
            ->and($byKey[$key]['sequenceable'])->toBeTrue()
            ->and(($byKey[$key]['visible'])())->toBeFalse();
    }
    // Bestaande acties blijven niet-sequenceable.
    expect($byKey['cancel']['sequenceable'] ?? false)->toBeFalse();
});

it('exposes a status select on set_fulfillment_status', function () {
    $action = collect(app(MobileApiRegistry::class)->orderActions())->firstWhere('key', 'set_fulfillment_status');
    $field = $action['fields'][0];
    expect($field['name'])->toBe('status')->and($field['type'])->toBe('select')
        ->and($field['options'])->toBeArray()->not->toBeEmpty();
});
