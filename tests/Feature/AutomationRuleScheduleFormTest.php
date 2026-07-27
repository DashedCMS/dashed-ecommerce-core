<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\EditAutomationRule;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\CreateAutomationRule;

/**
 * Task 7: het Filament schedule-subformulier voor tijd-triggers
 * (`type === 'time'`, `mode` 'relative'/'recurring'). Task 2 verborg
 * time.relative/time.recurring tijdelijk uit de trigger-select totdat dit
 * formulier bestond (zie TimeAutomationTriggersTest.php, bijgewerkt in deze
 * taak) — met dit scherm is die verberging niet langer nodig.
 *
 * Eigen actingAs-helper (i.p.v. de `actingAsCmsAdmin()` uit
 * AutomationRuleResourceTest.php) zodat dit bestand ook standalone draait,
 * ongeacht test-volgorde.
 */
beforeEach(function () {
    $user = User::factory()->create(['role' => 'admin']);
    test()->actingAs($user, 'sanctum');
});

it('bewaart een relatieve schedule via het formulier', function () {
    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Herinnering na aanmaak',
            'trigger' => 'time.relative',
            'schedule_anchor' => 'created_at',
            'schedule_amount' => 3,
            'schedule_unit' => 'days',
            'conditions' => [],
            'actions' => [['key' => 'mark_packed', 'params' => []]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = AutomationRule::query()->where('name', 'Herinnering na aanmaak')->firstOrFail();

    expect($rule->trigger)->toBe('time.relative')
        ->and($rule->schedule)->toBe([
            'mode' => 'relative', 'anchor' => 'created_at', 'amount' => 3, 'unit' => 'days',
        ]);
});

it('bewaart een terugkerende wekelijkse schedule via het formulier', function () {
    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Wekelijkse herinnering',
            'trigger' => 'time.recurring',
            'schedule_frequency' => 'weekly',
            'schedule_at' => '09:30',
            'schedule_weekday' => 2,
            'conditions' => [],
            'actions' => [['key' => 'mark_packed', 'params' => []]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = AutomationRule::query()->where('name', 'Wekelijkse herinnering')->firstOrFail();

    expect($rule->schedule)->toBe([
        'mode' => 'recurring', 'frequency' => 'weekly', 'at' => '09:30', 'weekday' => 2,
    ]);
});

it('bewaart een terugkerende dagelijkse schedule zonder weekday-sleutel', function () {
    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Dagelijkse herinnering',
            'trigger' => 'time.recurring',
            'schedule_frequency' => 'daily',
            'schedule_at' => '08:00',
            'conditions' => [],
            'actions' => [['key' => 'mark_packed', 'params' => []]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = AutomationRule::query()->where('name', 'Dagelijkse herinnering')->firstOrFail();

    // 'daily' heeft geen weekday nodig — TimeRuleScanner::recurringDue() valt
    // voor niet-weekly terug op 'true' zonder de weekday te lezen.
    expect($rule->schedule)->toBe(['mode' => 'recurring', 'frequency' => 'daily', 'at' => '08:00'])
        ->and($rule->schedule)->not->toHaveKey('weekday');
});

it('bewaart geen schedule voor een gewone event-trigger', function () {
    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Gewone event-regel',
            'trigger' => 'order.paid',
            'conditions' => [],
            'actions' => [['key' => 'mark_packed', 'params' => []]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = AutomationRule::query()->where('name', 'Gewone event-regel')->firstOrFail();

    expect($rule->schedule)->toBeNull();
});

it('weigert een onbekend schedule-anker', function () {
    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Ongeldig anker',
            'trigger' => 'time.relative',
            'schedule_anchor' => 'shipped_at', // niet in TimeAnchors::KEYS
            'schedule_amount' => 1,
            'schedule_unit' => 'days',
            'conditions' => [],
            'actions' => [['key' => 'mark_packed', 'params' => []]],
        ])
        ->call('create')
        ->assertHasFormErrors(['schedule_anchor']);

    expect(AutomationRule::query()->where('name', 'Ongeldig anker')->exists())->toBeFalse();
});

it('weigert een terugkerende schedule zonder geldig tijdstip (at)', function () {
    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Zonder tijdstip',
            'trigger' => 'time.recurring',
            'schedule_frequency' => 'daily',
            'schedule_at' => 'niet-een-tijd',
            'conditions' => [],
            'actions' => [['key' => 'mark_packed', 'params' => []]],
        ])
        ->call('create')
        ->assertHasFormErrors(['schedule_at']);

    expect(AutomationRule::query()->where('name', 'Zonder tijdstip')->exists())->toBeFalse();
});

it('weigert een wekelijkse schedule met een ongeldige weekday', function () {
    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Ongeldige weekday',
            'trigger' => 'time.recurring',
            'schedule_frequency' => 'weekly',
            'schedule_at' => '10:00',
            'schedule_weekday' => 9, // buiten 1..7
            'conditions' => [],
            'actions' => [['key' => 'mark_packed', 'params' => []]],
        ])
        ->call('create')
        ->assertHasFormErrors(['schedule_weekday']);

    expect(AutomationRule::query()->where('name', 'Ongeldige weekday')->exists())->toBeFalse();
});

it('vult het schedule-subformulier bij het bewerken van een bestaande relatieve regel, en bewaart een wijziging', function () {
    $rule = AutomationRule::create([
        'site_id' => 'site',
        'name' => 'Bestaande relatieve regel',
        'trigger' => 'time.relative',
        'conditions' => [],
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'schedule' => ['mode' => 'relative', 'anchor' => 'paid', 'amount' => 5, 'unit' => 'hours'],
        'is_active' => true,
    ]);

    Livewire::test(EditAutomationRule::class, ['record' => $rule->getRouteKey()])
        ->assertFormSet([
            'schedule_anchor' => 'paid',
            'schedule_amount' => 5,
            'schedule_unit' => 'hours',
        ])
        ->fillForm(['schedule_amount' => 7])
        ->call('save')
        ->assertHasNoFormErrors();

    $rule->refresh();

    expect($rule->schedule)->toBe(['mode' => 'relative', 'anchor' => 'paid', 'amount' => 7, 'unit' => 'hours']);
});
