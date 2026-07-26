<?php

use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Support\TimeAutomationTriggers;

it('registreert time.relative en time.recurring met type time', function () {
    $registry = app(MobileApiRegistry::class);
    TimeAutomationTriggers::register($registry);

    $relative = $registry->automationTrigger('time.relative');
    $recurring = $registry->automationTrigger('time.recurring');

    expect($relative)->not->toBeNull()
        ->and($relative['type'])->toBe('time')
        ->and($relative['mode'])->toBe('relative')
        ->and($relative['subject'])->toBe('order')
        ->and($relative['fields'])->toBeArray()
        ->and($relative)->not->toHaveKey('event');
    expect($recurring['type'])->toBe('time')
        ->and($recurring['mode'])->toBe('recurring');
});
