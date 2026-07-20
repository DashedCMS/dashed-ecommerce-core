<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\EditAutomationRule;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages\CreateAutomationRule;

/**
 * Task 7: het CMS-scherm waarin een beheerder automatiseringsregels bouwt
 * (trigger, voorwaarden, acties). De mobiele app leest deze regels alleen uit
 * en bedient enkel de aan/uit-schakelaar (Task 6) — het opstellen gebeurt hier.
 */
function actingAsCmsAdmin(): User
{
    $user = User::factory()->create(['role' => 'admin']);
    test()->actingAs($user, 'sanctum');

    return $user;
}

/**
 * Roept een private/protected static methode op AutomationRuleResource aan.
 * De methodes die hiermee getest worden (triggerOptions/automatableActionOptions/
 * resolveOptions/conditionValueOptions) zijn de exacte functies waarmee de
 * Select-velden in het formulier hun opties opbouwen — dit test dus het echte
 * gedrag, niet een schaduw-implementatie.
 */
function callAutomationResourceMethod(string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod(AutomationRuleResource::class, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke(null, ...$args);
}

it('is site-aware, read from a single model, wired into three pages', function () {
    expect(AutomationRuleResource::getModel())->toBe(AutomationRule::class)
        ->and(AutomationRuleResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});

it('exposes exactly the six order automation triggers to the trigger select', function () {
    $options = callAutomationResourceMethod('triggerOptions');

    expect(array_keys($options))->toEqualCanonicalizing([
        'order.created',
        'order.paid',
        'order.cancelled',
        'order.fulfillment_changed',
        'order.return_requested',
        'order.return_approved',
    ])
        // uit de registry, geen ruwe keys
        ->and($options['order.paid'])->toBe('Bestelling betaald');
});

it('only lists automatable actions in the action select, excluding cancel', function () {
    $options = callAutomationResourceMethod('automatableActionOptions');

    expect($options)->toHaveKey('mark_packed')
        ->and($options)->toHaveKey('regenerate_invoice')
        ->and($options)->toHaveKey('set_fulfillment_status')
        // niet-automatiseerbaar: onomkeerbaar / waarde niet vooraf bekend
        ->and($options)->not->toHaveKey('cancel')
        ->and($options)->not->toHaveKey('track_and_trace')
        ->and($options)->not->toHaveKey('manual_payment')
        ->and($options)->not->toHaveKey('payment_link')
        ->and($options)->not->toHaveKey('retour_status');
});

describe('polymorphic field/trigger options (array or callable)', function () {
    it('resolves options given as a plain array', function () {
        expect(callAutomationResourceMethod('resolveOptions', [['a' => 'A', 'b' => 'B']]))
            ->toBe(['a' => 'A', 'b' => 'B']);
    });

    it('resolves options given as a callable', function () {
        expect(callAutomationResourceMethod('resolveOptions', [fn () => ['x' => 'X']]))
            ->toBe(['x' => 'X']);
    });

    it('returns an empty array for neither an array nor a callable', function () {
        expect(callAutomationResourceMethod('resolveOptions', [null]))->toBe([]);
    });

    it('handles a real trigger field with array-form options (status)', function () {
        $options = callAutomationResourceMethod('conditionValueOptions', ['order.paid', 'status']);

        expect($options)->toHaveKey('paid')
            ->and($options['paid'])->toBe('Betaald');
    });

    it('handles a real trigger field with callable-form options (country) without crashing', function () {
        $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'site_id' => 'site', 'country' => 'NL']);

        $options = callAutomationResourceMethod('conditionValueOptions', ['order.paid', 'country']);

        expect($options)->toBe(['NL' => 'NL']);
    });
});

/**
 * De AANDACHTSPUNT uit de brief: de country/origin/payment_method-condition-
 * opties werden opgehaald zonder site-scope. Zonder fix zou de dropdown op
 * site A ook landen/origins van site B tonen (en een voorwaarde toestaan die
 * op de eigen site nooit matcht). Gefixt in OrderAutomationTriggers via
 * Order::thisSite() / PaymentMethod site_id-filter.
 */
/**
 * Order::boot() zet site_id altijd op Sites::getActive() bij het aanmaken
 * (ongeacht wat je meegeeft) — dus om echt een order "van een andere site" te
 * simuleren, wisselen we de actieve site tijdelijk om, exact zoals dat ook in
 * een echte multi-site-installatie gebeurt (elke site heeft zijn eigen
 * actieve-site-context op het moment dat de order ontstaat).
 */
it('only offers country options from the active site, not from another site', function () {
    Order::create(['email' => 'eigen@example.com', 'status' => 'paid', 'country' => 'NL']);

    config(['dashed-core.dashed_site_id' => 'andere-site']);
    Order::create(['email' => 'ander@example.com', 'status' => 'paid', 'country' => 'DE']);
    config(['dashed-core.dashed_site_id' => 'site']);

    $options = callAutomationResourceMethod('conditionValueOptions', ['order.paid', 'country']);

    expect($options)->toBe(['NL' => 'NL'])
        ->and($options)->not->toHaveKey('DE');
});

it('only offers origin options from the active site, not from another site', function () {
    Order::create(['email' => 'eigen@example.com', 'status' => 'paid', 'order_origin' => 'own']);

    config(['dashed-core.dashed_site_id' => 'andere-site']);
    Order::create(['email' => 'ander@example.com', 'status' => 'paid', 'order_origin' => 'pos']);
    config(['dashed-core.dashed_site_id' => 'site']);

    $options = callAutomationResourceMethod('conditionValueOptions', ['order.paid', 'origin']);

    expect($options)->toBe(['own' => 'Own'])
        ->and($options)->not->toHaveKey('pos');
});

it('creates a rule via the resource, correctly persisting trigger, conditions and actions', function () {
    actingAsCmsAdmin();

    Livewire::test(CreateAutomationRule::class)
        ->fillForm([
            'name' => 'Fulfilmentstatus zetten bij betaling',
            'trigger' => 'order.paid',
            'is_active' => true,
            'conditions' => [
                ['field' => 'total', 'operator' => 'gt', 'value' => '50'],
            ],
            'actions' => [
                ['key' => 'set_fulfillment_status', 'params' => ['status' => 'packed']],
                ['key' => 'mark_packed', 'params' => []],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $rule = AutomationRule::query()->where('name', 'Fulfilmentstatus zetten bij betaling')->firstOrFail();

    expect($rule->trigger)->toBe('order.paid')
        ->and($rule->is_active)->toBeTrue()
        ->and($rule->conditions)->toHaveCount(1)
        ->and($rule->conditions[0]['field'])->toBe('total')
        ->and($rule->conditions[0]['operator'])->toBe('gt')
        // het 'total'-veld is van het type 'number': de numerieke waarde-
        // variant dehydreert naar een echt getal, niet de opgegeven string.
        ->and((float) $rule->conditions[0]['value'])->toBe(50.0)
        ->and($rule->actions)->toHaveCount(2)
        ->and($rule->actions[0]['key'])->toBe('set_fulfillment_status')
        ->and($rule->actions[0]['params'])->toBe(['status' => 'packed'])
        ->and($rule->actions[1]['key'])->toBe('mark_packed')
        // 'mark_packed' heeft geen parameter-velden, dus de params-groep
        // dehydreert niet — AutomationEngine leest dit toch als `?? []`.
        ->and($rule->actions[1]['params'] ?? [])->toBe([]);
});

it('refuses to select a non-automatable action key, since the select never offers it', function () {
    $options = callAutomationResourceMethod('automatableActionOptions');

    expect($options)->not->toHaveKey('cancel');
});

it('edits an existing rule and updates its stored conditions/actions', function () {
    actingAsCmsAdmin();

    $rule = AutomationRule::create([
        'site_id' => 'site',
        'name' => 'Bij annulering',
        'trigger' => 'order.cancelled',
        'conditions' => [],
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'is_active' => true,
    ]);

    Livewire::test(EditAutomationRule::class, ['record' => $rule->getRouteKey()])
        ->fillForm([
            'name' => 'Bij annulering (bijgewerkt)',
            'is_active' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $rule->refresh();

    expect($rule->name)->toBe('Bij annulering (bijgewerkt)')
        ->and($rule->is_active)->toBeFalse()
        // trigger/acties blijven inhoudelijk ongewijzigd
        ->and($rule->trigger)->toBe('order.cancelled')
        ->and($rule->actions)->toHaveCount(1)
        ->and($rule->actions[0]['key'])->toBe('mark_packed')
        ->and($rule->actions[0]['params'] ?? [])->toBe([]);
});

it('shows the recent runs of a rule via the runs relation manager relationship', function () {
    $rule = AutomationRule::create([
        'site_id' => 'site',
        'name' => 'Bij betaling',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'is_active' => true,
    ]);

    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'site_id' => 'site']);

    $run = AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'site',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'order.paid',
        'status' => AutomationRuleRun::STATUS_SUCCESS,
        'results' => [],
        'error' => null,
    ]);

    expect($rule->runs()->count())->toBe(1)
        ->and($rule->runs->first()->is($run))->toBeTrue();
});
