<?php

declare(strict_types=1);

use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Support\TimeAutomationTriggers;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource;

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

it('verbergt tijd-triggers in de Filament trigger-select totdat het schedule-subformulier klaar is', function () {
    $registry = app(MobileApiRegistry::class);
    TimeAutomationTriggers::register($registry);

    // Triggers zijn in de registry
    expect($registry->automationTrigger('time.relative'))->not->toBeNull()
        ->and($registry->automationTrigger('time.recurring'))->not->toBeNull();

    // Maar NIET in de UI-opties
    $reflection = new ReflectionMethod(AutomationRuleResource::class, 'triggerOptions');
    $reflection->setAccessible(true);
    $options = $reflection->invoke(null);

    expect($options)->not->toHaveKey('time.relative')
        ->and($options)->not->toHaveKey('time.recurring');
});
