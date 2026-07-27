<?php

declare(strict_types=1);

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Dashed\DashedEcommerceCore\Support\Automation\TimeRuleScanner;

/**
 * Task 5 (B2): TimeRuleScanner::recurringDue() / recurringCandidates() voor
 * terugkerende tijd-regels ("elke ochtend 09:00" / "elke maandag 08:00").
 *
 * Er is geen Order::factory() in dit package (zie TimeRuleScannerRelativeTest),
 * dus we bouwen orders met Order::create() en zetten site_id/created_at
 * nadien via forceFill()+save() — Order::boot() overschrijft beide bij het
 * aanmaken (site_id via Sites::getActive(), created_at via Eloquent's
 * timestamps).
 */
function recurringScannerOrder(string $siteId, Carbon $createdAt): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
    ]);
    $order->forceFill(['site_id' => $siteId, 'created_at' => $createdAt])->save();

    return $order->fresh();
}

function dailyRule(string $at = '09:00'): AutomationRule
{
    return AutomationRule::create([
        'site_id' => 'main', 'name' => 'D', 'trigger' => 'time.recurring',
        'conditions' => [], 'actions' => [],
        'schedule' => ['mode' => 'recurring', 'frequency' => 'daily', 'at' => $at],
        'is_active' => true,
    ]);
}

function weeklyRule(int $weekday, string $at = '08:00'): AutomationRule
{
    return AutomationRule::create([
        'site_id' => 'main', 'name' => 'W', 'trigger' => 'time.recurring',
        'conditions' => [], 'actions' => [],
        'schedule' => ['mode' => 'recurring', 'frequency' => 'weekly', 'at' => $at, 'weekday' => $weekday],
        'is_active' => true,
    ]);
}

it('recurringDue: daily pas na het tijdstip', function () {
    expect(TimeRuleScanner::recurringDue(dailyRule('09:00'), Carbon::parse('2026-03-01 08:00')))->toBeFalse();
    expect(TimeRuleScanner::recurringDue(dailyRule('09:00'), Carbon::parse('2026-03-01 09:30')))->toBeTrue();
});

it('recurringDue: weekly alleen op de juiste weekdag', function () {
    $rule = weeklyRule(1); // maandag

    expect(TimeRuleScanner::recurringDue($rule, Carbon::parse('2026-03-02 08:30')))->toBeTrue();  // ma
    expect(TimeRuleScanner::recurringDue($rule, Carbon::parse('2026-03-03 08:30')))->toBeFalse(); // di
});

it('recurringDue: weekly ook vóór het tijdstip op de juiste dag is false', function () {
    $rule = weeklyRule(1, '08:00'); // maandag 08:00

    expect(TimeRuleScanner::recurringDue($rule, Carbon::parse('2026-03-02 07:30')))->toBeFalse();
});

it('kandidaten: één keer per dag, morgen weer', function () {
    $rule = dailyRule('09:00');
    $order = recurringScannerOrder('main', Carbon::parse('2026-02-20'));
    $today = Carbon::parse('2026-03-01 09:30');

    expect(TimeRuleScanner::recurringCandidates($rule, $today)->pluck('id'))->toContain($order->id);

    AutomationRuleRun::create([
        'rule_id' => $rule->id, 'site_id' => 'main', 'subject_type' => Order::class,
        'subject_id' => $order->id, 'trigger' => 'time.recurring',
        'status' => AutomationRuleRun::STATUS_SUCCESS, 'results' => [], 'created_at' => $today,
    ]);

    // zelfde dag: niet opnieuw
    expect(TimeRuleScanner::recurringCandidates($rule, $today->copy()->addHours(2))->pluck('id'))
        ->not->toContain($order->id);

    // volgende dag: weer wel
    expect(TimeRuleScanner::recurringCandidates($rule, Carbon::parse('2026-03-02 09:30'))->pluck('id'))
        ->toContain($order->id);
});

it('kandidaten: buiten schedule levert een lege collectie', function () {
    $rule = dailyRule('09:00');
    recurringScannerOrder('main', Carbon::parse('2026-02-20'));

    expect(TimeRuleScanner::recurringCandidates($rule, Carbon::parse('2026-03-01 08:00')))->toBeEmpty();
});

it('kandidaten: weekly dedup geldt per week, niet per dag', function () {
    $rule = weeklyRule(1, '08:00'); // elke maandag 08:00
    $order = recurringScannerOrder('main', Carbon::parse('2026-02-01'));
    $monday = Carbon::parse('2026-03-02 08:30');

    expect(TimeRuleScanner::recurringCandidates($rule, $monday)->pluck('id'))->toContain($order->id);

    AutomationRuleRun::create([
        'rule_id' => $rule->id, 'site_id' => 'main', 'subject_type' => Order::class,
        'subject_id' => $order->id, 'trigger' => 'time.recurring',
        'status' => AutomationRuleRun::STATUS_SUCCESS, 'results' => [], 'created_at' => $monday,
    ]);

    // later diezelfde week: nog steeds uitgesloten (geen andere maandag, maar
    // stel dat de scan opnieuw draait diezelfde dag)
    expect(TimeRuleScanner::recurringCandidates($rule, $monday->copy()->addHours(1))->pluck('id'))
        ->not->toContain($order->id);

    // volgende week maandag: weer een kandidaat
    expect(TimeRuleScanner::recurringCandidates($rule, Carbon::parse('2026-03-09 08:30'))->pluck('id'))
        ->toContain($order->id);
});

it('kandidaten: sluit orders van een andere site uit', function () {
    $rule = dailyRule('09:00');
    $other = recurringScannerOrder('other', Carbon::parse('2026-02-20'));

    expect(TimeRuleScanner::recurringCandidates($rule, Carbon::parse('2026-03-01 09:30'))->pluck('id'))
        ->not->toContain($other->id);
});

it('kandidaten: orders buiten de horizon worden uitgesloten', function () {
    $rule = dailyRule('09:00');
    $now = Carbon::parse('2026-03-01 09:30');
    $tooOld = recurringScannerOrder('main', $now->copy()->subDays(120));

    expect(TimeRuleScanner::recurringCandidates($rule, $now)->pluck('id'))->not->toContain($tooOld->id);
});
