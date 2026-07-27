<?php

declare(strict_types=1);

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Dashed\DashedEcommerceCore\Support\Automation\RuleDryRun;

/**
 * Task 8 (B2): de droogloop-uitbreiding voor tijd-regels. RuleDryRun::forSchedule()
 * hergebruikt TimeRuleScanner (kandidaten) + ConditionEvaluator/AutomationContext
 * (voorwaarden) — exact hetzelfde drietal als RunTimeBasedAutomationRules::handle(),
 * de échte scan-job — maar roept nooit AutomationEngine::run() of een actie-`handle`
 * aan. De kern-eis wordt hier op twee onafhankelijke manieren hard vastgelegd: een
 * spy die faalt zodra hij wordt aangeroepen, én een telling dat er geen
 * AutomationRuleRun bijkomt.
 *
 * Order::create() + forceFill(['site_id'=>.., 'created_at'=>..])->save() in plaats
 * van Order::factory() (die niet bestaat in dit package) — Order::boot()
 * overschrijft beide velden bij het aanmaken. Zie TimeRuleScannerRelativeTest.php.
 */
function scheduleDryRunOrder(string $siteId, Carbon $createdAt, array $attributes = []): Order
{
    $order = Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'total' => 100,
    ], $attributes));
    $order->forceFill(['site_id' => $siteId, 'created_at' => $createdAt])->save();

    return $order->fresh();
}

function scheduleDryRunRelativeRule(array $attributes = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => 'main',
        'name' => 'Tijd-droogloop-regel',
        'trigger' => 'time.relative',
        'conditions' => [],
        'actions' => [],
        'schedule' => ['mode' => 'relative', 'anchor' => 'created_at', 'amount' => 3, 'unit' => 'days'],
        'is_active' => true,
    ], $attributes));
}

function scheduleDryRunRecurringRule(array $attributes = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => 'main',
        'name' => 'Terugkerende droogloop-regel',
        'trigger' => 'time.recurring',
        'conditions' => [],
        'actions' => [],
        'schedule' => ['mode' => 'recurring', 'frequency' => 'daily', 'at' => '09:00'],
        'is_active' => true,
    ], $attributes));
}

/**
 * Registreert een order-actie waarvan de handler faalt zodra hij wordt
 * aangeroepen — forSchedule() mag deze closure onder geen beding uitvoeren.
 */
function registerScheduleNeverCalledAction(string $key, string $label = 'Spy-actie'): void
{
    app(MobileApiRegistry::class)->registerOrderActions([
        [
            'key' => $key,
            'label' => $label,
            'automatable' => true,
            'visible' => fn () => false,
            'handle' => function () use ($key): void {
                throw new RuntimeException("forSchedule() riep de handle van '{$key}' aan — dit mag nooit gebeuren.");
            },
        ],
    ]);
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('telt zou-vuren-kandidaten voor een relatieve tijd-regel zonder iets uit te voeren', function () {
    Carbon::setTestNow('2026-03-01 10:00');
    registerScheduleNeverCalledAction('mark_packed', 'Markeer als ingepakt');

    $due1 = scheduleDryRunOrder('main', Carbon::parse('2026-02-25'));
    $due2 = scheduleDryRunOrder('main', Carbon::parse('2026-02-25'));
    scheduleDryRunOrder('main', Carbon::parse('2026-02-28')); // te nieuw, < 3 dagen delay

    $rule = scheduleDryRunRelativeRule([
        'actions' => [['key' => 'mark_packed', 'params' => []]],
    ]);

    $result = RuleDryRun::forSchedule($rule, Carbon::now());

    expect($result['mode'])->toBe('relative')
        ->and($result['would_fire_count'])->toBe(2)
        ->and($result['actions'])->toBe([
            ['key' => 'mark_packed', 'label' => 'Markeer als ingepakt', 'params' => []],
        ])
        // Kern-eis: geen enkele AutomationRuleRun is aangemaakt — forSchedule()
        // is een telling, geen uitvoerpad.
        ->and(AutomationRuleRun::query()->count())->toBe(0);
});

it('telt geen order mee die niet aan de voorwaarden van de regel voldoet', function () {
    Carbon::setTestNow('2026-03-01 10:00');

    scheduleDryRunOrder('main', Carbon::parse('2026-02-25'), ['total' => 150]);
    scheduleDryRunOrder('main', Carbon::parse('2026-02-25'), ['total' => 150]);
    scheduleDryRunOrder('main', Carbon::parse('2026-02-25'), ['total' => 10]); // matcht de conditie niet

    $rule = scheduleDryRunRelativeRule([
        'conditions' => [['field' => 'total', 'operator' => 'gt', 'value' => 100]],
    ]);

    $result = RuleDryRun::forSchedule($rule, Carbon::now());

    expect($result['would_fire_count'])->toBe(2);
});

it('sluit orders van een andere site uit, net als TimeRuleScanner', function () {
    Carbon::setTestNow('2026-03-01 10:00');

    scheduleDryRunOrder('main', Carbon::parse('2026-02-25'));
    scheduleDryRunOrder('other', Carbon::parse('2026-02-25'));

    $result = RuleDryRun::forSchedule(scheduleDryRunRelativeRule(), Carbon::now());

    expect($result['would_fire_count'])->toBe(1);
});

it('telt geen order mee die al een geslaagde run heeft', function () {
    Carbon::setTestNow('2026-03-01 10:00');

    $rule = scheduleDryRunRelativeRule();
    $order = scheduleDryRunOrder('main', Carbon::parse('2026-02-25'));
    AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'main',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'time.relative',
        'status' => AutomationRuleRun::STATUS_SUCCESS,
        'results' => [],
    ]);

    $result = RuleDryRun::forSchedule($rule, Carbon::now());

    expect($result['would_fire_count'])->toBe(0)
        ->and($result['actions'])->toBe([]);
});

it('telt zou-vuren-kandidaten voor een terugkerende tijd-regel zonder iets uit te voeren', function () {
    Carbon::setTestNow('2026-03-01 10:00'); // na 09:00, dus recurringDue() is waar
    registerScheduleNeverCalledAction('send_reminder', 'Stuur herinnering');

    scheduleDryRunOrder('main', Carbon::parse('2026-02-20'));
    scheduleDryRunOrder('main', Carbon::parse('2026-02-21'));

    $rule = scheduleDryRunRecurringRule([
        'actions' => [['key' => 'send_reminder', 'params' => []]],
    ]);

    $result = RuleDryRun::forSchedule($rule, Carbon::now());

    expect($result['mode'])->toBe('recurring')
        ->and($result['would_fire_count'])->toBe(2)
        ->and($result['actions'])->toBe([
            ['key' => 'send_reminder', 'label' => 'Stuur herinnering', 'params' => []],
        ])
        ->and(AutomationRuleRun::query()->count())->toBe(0);
});

it('geeft would_fire_count 0 wanneer de terugkerende cadans nog niet bereikt is', function () {
    Carbon::setTestNow('2026-03-01 08:00'); // vóór 09:00
    scheduleDryRunOrder('main', Carbon::parse('2026-02-20'));

    $result = RuleDryRun::forSchedule(scheduleDryRunRecurringRule(), Carbon::now());

    expect($result['would_fire_count'])->toBe(0)
        ->and($result['actions'])->toBe([]);
});

it('geeft would_fire_count 0 en geen acties bij een ontbrekende of ongeldige schedule.mode', function () {
    Carbon::setTestNow('2026-03-01 10:00');
    scheduleDryRunOrder('main', Carbon::parse('2026-02-25'));

    $rule = scheduleDryRunRelativeRule([
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'schedule' => ['mode' => 'onbekend'],
    ]);

    $result = RuleDryRun::forSchedule($rule, Carbon::now());

    // mode weerspiegelt de opgeslagen (ongeldige) waarde onveranderd — enkel
    // de kandidatenselectie valt terug op een lege lijst (net als
    // TimeRuleScanner zelf bij een ongeldige schedule).
    expect($result['mode'])->toBe('onbekend')
        ->and($result['would_fire_count'])->toBe(0)
        ->and($result['actions'])->toBe([]);
});

it('roept nooit een actie-handle aan, ook niet wanneer meerdere orders en acties matchen', function () {
    Carbon::setTestNow('2026-03-01 10:00');
    $calls = 0;
    app(MobileApiRegistry::class)->registerOrderActions([
        [
            'key' => 'counting_spy',
            'label' => 'Tellende spy',
            'automatable' => true,
            'visible' => fn () => false,
            'handle' => function () use (&$calls): void {
                $calls++;
            },
        ],
    ]);

    scheduleDryRunOrder('main', Carbon::parse('2026-02-25'));
    scheduleDryRunOrder('main', Carbon::parse('2026-02-24'));

    $rule = scheduleDryRunRelativeRule([
        'actions' => [
            ['key' => 'counting_spy', 'params' => []],
            ['key' => 'counting_spy', 'params' => ['x' => 1]],
        ],
    ]);

    $result = RuleDryRun::forSchedule($rule, Carbon::now());

    expect($calls)->toBe(0)
        ->and($result['would_fire_count'])->toBe(2)
        ->and($result['actions'])->toHaveCount(2)
        ->and(AutomationRuleRun::query()->count())->toBe(0);
});
