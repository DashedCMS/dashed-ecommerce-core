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

/**
 * Task 2 verborg tijd-triggers hier tijdelijk uit de trigger-select totdat
 * het schedule-subformulier bestond (zonder zo'n formulier zou een beheerder
 * een tijd-regel kunnen kiezen zonder er een schedule aan te kunnen hangen).
 * Task 7 heeft dat schedule-subformulier nu gebouwd (de 'Planning'-Section in
 * AutomationRuleResource::form(), zichtbaar zodra de trigger `type === 'time'`
 * is) — de tijdelijke verberging is dus niet langer nodig en time.relative/
 * time.recurring horen weer gewoon in de UI-opties te staan.
 */
it('toont tijd-triggers in de Filament trigger-select, nu het schedule-subformulier bestaat', function () {
    $registry = app(MobileApiRegistry::class);
    TimeAutomationTriggers::register($registry);

    // Triggers zijn in de registry
    expect($registry->automationTrigger('time.relative'))->not->toBeNull()
        ->and($registry->automationTrigger('time.recurring'))->not->toBeNull();

    // En nu ook in de UI-opties
    $reflection = new ReflectionMethod(AutomationRuleResource::class, 'triggerOptions');
    $reflection->setAccessible(true);
    $options = $reflection->invoke(null);

    expect($options)->toHaveKey('time.relative')
        ->and($options)->toHaveKey('time.recurring')
        ->and($options['time.relative'])->toBe('Op tijd na een moment')
        ->and($options['time.recurring'])->toBe('Terugkerend op een tijdstip');
});
