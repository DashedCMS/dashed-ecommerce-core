<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\CreateAutomationRule;

/**
 * Task 7 (B3): de klant-/voorraad-triggers (Task 4/6) en de `notify`-actie
 * (Task 3) in de Filament-regelbouwer. De trigger-select en het
 * conditie-veldmechanisme zijn generiek (lezen alles uit
 * MobileApiRegistry::automationTriggers()), dus dit dekt vooral dat er geen
 * verborgen filter meer is die deze vier triggers uitsluit, en dat het
 * schedule-subformulier (Task 7/B2) zich niet per ongeluk ook voor deze
 * triggers opent — `stock.low`/`stock.back` hebben `type => 'stock'`,
 * `customer.new`/`customer.nth_order` hebben helemaal geen `type`, dus
 * `isTimeTrigger()` (die uitsluitend op `type === 'time'` test) laat de
 * 'Planning'-Section voor geen van beide zien.
 */
function actingAsB3CmsAdmin(): User
{
    $user = User::factory()->create(['role' => 'admin']);
    test()->actingAs($user, 'sanctum');

    return $user;
}

function callB3ResourceMethod(string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod(AutomationRuleResource::class, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(null, ...$args);
}

it('exposes the four new B3 triggers (customer + stock) in the trigger select', function () {
    $options = callB3ResourceMethod('triggerOptions');

    expect($options)->toHaveKey('customer.new')
        ->and($options)->toHaveKey('customer.nth_order')
        ->and($options)->toHaveKey('stock.low')
        ->and($options)->toHaveKey('stock.back')
        ->and($options['customer.new'])->toBe('Nieuwe klant')
        ->and($options['stock.low'])->toBe('Voorraad laag');
});

it('renders the product condition fields (stock/price/name/sku) for a stock trigger', function () {
    $fields = callB3ResourceMethod('conditionFieldOptions', ['stock.low']);

    expect(array_keys($fields))->toEqualCanonicalizing(['stock', 'price', 'name', 'sku'])
        ->and(callB3ResourceMethod('conditionFieldType', ['stock.low', 'stock']))->toBe('number')
        ->and(callB3ResourceMethod('conditionFieldType', ['stock.low', 'sku']))->toBe('text');
});

it('renders the customer condition fields (order_count/total_spend/email/is_registered) for a customer trigger', function () {
    $fields = callB3ResourceMethod('conditionFieldOptions', ['customer.new']);

    expect(array_keys($fields))->toEqualCanonicalizing(['order_count', 'total_spend', 'email', 'is_registered'])
        ->and(callB3ResourceMethod('conditionFieldType', ['customer.new', 'order_count']))->toBe('number')
        ->and(callB3ResourceMethod('conditionFieldType', ['customer.new', 'is_registered']))->toBe('boolean');
});

it('never shows the schedule-form for a stock trigger, since it has no type === time', function () {
    // Zelfde mechanisme als de Planning-Section's `visible()`-closure in
    // AutomationRuleResource::form(): isTimeTrigger() bepaalt zichtbaarheid,
    // niet een aparte "is dit een tijd- of scan-trigger"-lijst.
    expect(callB3ResourceMethod('isTimeTrigger', ['stock.low']))->toBeFalse()
        ->and(callB3ResourceMethod('isTimeTrigger', ['stock.back']))->toBeFalse()
        ->and(callB3ResourceMethod('isTimeTrigger', ['customer.new']))->toBeFalse()
        ->and(callB3ResourceMethod('isTimeTrigger', ['customer.nth_order']))->toBeFalse();
});

it('saves a rule with trigger stock.low + action notify, without any schedule fields', function () {
    actingAsB3CmsAdmin();

    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Waarschuw bij lage voorraad',
            'trigger' => 'stock.low',
            'conditions' => [
                ['field' => 'stock', 'operator' => 'lt', 'value' => '5'],
            ],
            'actions' => [
                ['key' => 'notify', 'params' => ['title' => 'Lage voorraad', 'body' => 'Check het magazijn.']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = AutomationRule::query()->where('name', 'Waarschuw bij lage voorraad')->firstOrFail();

    expect($rule->trigger)->toBe('stock.low')
        // KRITIEK: geen schedule/anker/interval-velden voor een voorraad-
        // trigger — die horen exclusief bij time.relative/time.recurring.
        ->and($rule->schedule)->toBeNull()
        ->and($rule->conditions)->toHaveCount(1)
        ->and($rule->conditions[0]['field'])->toBe('stock')
        ->and($rule->actions)->toHaveCount(1)
        ->and($rule->actions[0]['key'])->toBe('notify')
        ->and($rule->actions[0]['params']['title'])->toBe('Lage voorraad');
});

it('saves a rule with trigger customer.new + action notify', function () {
    actingAsB3CmsAdmin();

    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Welkom nieuwe klant',
            'trigger' => 'customer.new',
            'conditions' => [
                ['field' => 'order_count', 'operator' => 'eq', 'value' => '1'],
            ],
            'actions' => [
                ['key' => 'notify', 'params' => ['title' => 'Nieuwe klant']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = AutomationRule::query()->where('name', 'Welkom nieuwe klant')->firstOrFail();

    expect($rule->trigger)->toBe('customer.new')
        ->and($rule->schedule)->toBeNull()
        ->and($rule->conditions[0]['field'])->toBe('order_count')
        ->and($rule->actions[0]['key'])->toBe('notify');
});

it('offers notify as a selectable automatable action', function () {
    $options = callB3ResourceMethod('automatableActionOptions');

    expect($options)->toHaveKey('notify')
        ->and($options['notify'])->toBe('Stuur melding');
});
