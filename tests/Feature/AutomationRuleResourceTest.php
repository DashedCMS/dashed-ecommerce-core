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

/**
 * Task 8: de droogloop-rijactie ("Testen") op deze resource. RuleDryRun zelf
 * (match/context/acties, nooit een handle aanroepen) wordt in RuleDryRunTest
 * getest; hier alleen de CMS-specifieke bouwstenen — orderkeuze (site-scope,
 * factuurnummer/ID-zoeken) en de weergave-mapping van RuleDryRun's resultaat.
 */
describe('dry run row action building blocks', function () {
    it('labels an order by its invoice number, falling back to #id for a proforma/return placeholder', function () {
        $withInvoice = Order::create(['email' => 'a@example.com', 'status' => 'paid', 'invoice_id' => 'F2026-001']);
        $proforma = Order::create(['email' => 'b@example.com', 'status' => 'concept', 'invoice_id' => 'PROFORMA']);

        expect(callAutomationResourceMethod('dryRunOrderLabel', [$withInvoice]))
            ->toBe("F2026-001 — {$withInvoice->name}")
            ->and(callAutomationResourceMethod('dryRunOrderLabel', [$proforma]))
            ->toBe("#{$proforma->id} — {$proforma->name}")
            ->and(callAutomationResourceMethod('dryRunOrderLabel', [null]))
            ->toBeNull();
    });

    /**
     * BEVINDING C1: dryRunResultSchema()/getOptionLabelUsing haalden de order
     * op met Order::find() zonder site-check — enkel de zoeksuggesties
     * (dryRunOrderOptions, hierboven) filterden op site_id. Een beheerder kon
     * zo via Livewire-state een order-ID van een ándere site injecteren en de
     * droogloop daartegen laten draaien. dryRunFindOrder() is nu de ENIGE
     * plek waar de droogloop een order ophaalt, en filtert altijd op de site
     * van de regel.
     */
    it('scopes the order lookup for the dry run result to the rule’s own site, treating another site’s order as not found', function () {
        $rule = AutomationRule::create([
            'site_id' => 'site',
            'name' => 'Regel',
            'trigger' => 'order.paid',
            'conditions' => [],
            'actions' => [],
            'is_active' => true,
        ]);

        $ownOrder = Order::create(['email' => 'eigen@example.com', 'status' => 'paid']);

        config(['dashed-core.dashed_site_id' => 'andere-site']);
        $otherSiteOrder = Order::create(['email' => 'ander@example.com', 'status' => 'paid']);
        config(['dashed-core.dashed_site_id' => 'site']);

        expect(callAutomationResourceMethod('dryRunFindOrder', [$rule, $otherSiteOrder->id]))
            ->toBeNull()
            ->and(callAutomationResourceMethod('dryRunFindOrder', [$rule, $ownOrder->id])?->is($ownOrder))
            ->toBeTrue();
    });

    it('only offers orders from the rule’s own site when searching by invoice number or id', function () {
        $rule = AutomationRule::create([
            'site_id' => 'site',
            'name' => 'Regel',
            'trigger' => 'order.paid',
            'conditions' => [],
            'actions' => [],
            'is_active' => true,
        ]);

        $ownOrder = Order::create(['email' => 'eigen@example.com', 'status' => 'paid', 'invoice_id' => 'F2026-100']);

        config(['dashed-core.dashed_site_id' => 'andere-site']);
        Order::create(['email' => 'ander@example.com', 'status' => 'paid', 'invoice_id' => 'F2026-100-B']);
        config(['dashed-core.dashed_site_id' => 'site']);

        $byInvoice = callAutomationResourceMethod('dryRunOrderOptions', [$rule, 'F2026-100']);
        $byId = callAutomationResourceMethod('dryRunOrderOptions', [$rule, (string) $ownOrder->id]);

        expect($byInvoice)->toBe([$ownOrder->id => callAutomationResourceMethod('dryRunOrderLabel', [$ownOrder])])
            ->and($byId)->toBe([$ownOrder->id => callAutomationResourceMethod('dryRunOrderLabel', [$ownOrder])]);
    });

    it('maps a context array to human-readable field labels and value strings for display', function () {
        $context = [
            'total' => 123.45,
            'has_discount_code' => true,
            'country' => null,
        ];

        $display = callAutomationResourceMethod('dryRunContextForDisplay', [$context]);

        expect($display)->toHaveKey(callAutomationResourceMethod('conditionFieldLabel', ['total']))
            ->and($display[callAutomationResourceMethod('conditionFieldLabel', ['total'])])->toBe('123.45')
            ->and($display[callAutomationResourceMethod('conditionFieldLabel', ['has_discount_code'])])->toBe('waar')
            ->and($display[callAutomationResourceMethod('conditionFieldLabel', ['country'])])->toBe('-');
    });

    it('describes matched actions with their params, and a clear empty state for no actions or no match', function () {
        $withParams = callAutomationResourceMethod('dryRunActionsForDisplay', [
            [['key' => 'set_fulfillment_status', 'label' => 'Fulfilment-status wijzigen', 'params' => ['status' => 'packed']]],
            true,
        ]);
        $withoutParams = callAutomationResourceMethod('dryRunActionsForDisplay', [
            [['key' => 'mark_packed', 'label' => 'Markeer als ingepakt', 'params' => []]],
            true,
        ]);
        $noActionsMatched = callAutomationResourceMethod('dryRunActionsForDisplay', [[], true]);
        $noActionsUnmatched = callAutomationResourceMethod('dryRunActionsForDisplay', [[], false]);

        expect($withParams)->toBe(['Fulfilment-status wijzigen ({"status":"packed"})'])
            ->and($withoutParams)->toBe(['Markeer als ingepakt'])
            ->and($noActionsMatched)->toBe(['Deze regel heeft geen acties.'])
            ->and($noActionsUnmatched)->toBe(['Niet van toepassing — de regel matcht niet.']);
    });

    /**
     * BEVINDING C2: wanneer undeterminable_fields niet leeg is, mag de
     * "geen acties"-boodschap niet doen alsof "de regel matcht niet" een
     * vaststaand feit is (dat weten we juist niet zeker).
     */
    it('does not claim "regel matcht niet" for an empty action list when the fields are undeterminable', function () {
        $undeterminedNoActions = callAutomationResourceMethod('dryRunActionsForDisplay', [[], false, ['new_status']]);

        expect($undeterminedNoActions)->toBe(['Deze regel heeft geen acties.']);
    });
});

/**
 * BEVINDING C2: dryRunMatchDisplay() is de plek waar RuleDryRun's rauwe
 * `matched`-boolean vertaald wordt naar wat de beheerder daadwerkelijk te
 * zien krijgt. Bij undeterminable_fields mag dat nooit een definitief
 * "Nee, deze regel zou niet draaien" zijn.
 */
describe('dry run match display (C2 — geen misleidend "matched: false")', function () {
    it('shows a definitive yes/no when there are no undeterminable fields', function () {
        $matched = callAutomationResourceMethod('dryRunMatchDisplay', [
            ['matched' => true, 'undeterminable_fields' => [], 'context' => [], 'actions' => []],
        ]);
        $unmatched = callAutomationResourceMethod('dryRunMatchDisplay', [
            ['matched' => false, 'undeterminable_fields' => [], 'context' => [], 'actions' => []],
        ]);

        expect($matched)->toBe(['label' => 'Ja, deze regel zou draaien', 'color' => 'success'])
            ->and($unmatched)->toBe(['label' => 'Nee, deze regel zou niet draaien', 'color' => 'gray']);
    });

    it('shows a warning instead of "Nee" when the raw match is false but fields are undeterminable, naming the fields', function () {
        $display = callAutomationResourceMethod('dryRunMatchDisplay', [
            ['matched' => false, 'undeterminable_fields' => ['new_status'], 'context' => [], 'actions' => []],
        ]);

        expect($display['color'])->toBe('warning')
            ->and($display['label'])->not->toContain('Nee, deze regel zou niet draaien')
            ->and($display['label'])->toContain(callAutomationResourceMethod('conditionFieldLabel', ['new_status']));
    });
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
