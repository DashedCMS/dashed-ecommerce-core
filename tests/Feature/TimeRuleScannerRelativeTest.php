<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Dashed\DashedEcommerceCore\Support\Automation\TimeRuleScanner;

/**
 * Task 4 (B2): TimeRuleScanner::relativeCandidates() selecteert, voor een
 * relatieve tijd-regel ("N [uren|dagen] na [anker]"), de orders van de site
 * van de regel die nu in aanmerking komen om te vuren: ankertijd in
 * [$now - HORIZON_DAYS, $now - delay], zonder een bestaande geslaagde run
 * voor (regel, order).
 *
 * Er is geen Order::factory() in dit package (zie TimeAnchorsTest), dus we
 * bouwen orders met Order::create() en zetten site_id/created_at nadien via
 * forceFill()+save() — Order::boot() overschrijft beide bij het aanmaken
 * (site_id via Sites::getActive(), created_at via Eloquent's timestamps).
 */
function scannerOrder(string $siteId, Carbon $createdAt): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
    ]);
    $order->forceFill(['site_id' => $siteId, 'created_at' => $createdAt])->save();

    return $order->fresh();
}

function relRule(array $attributes = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => 'main', 'name' => 'R', 'trigger' => 'time.relative',
        'conditions' => [], 'actions' => [],
        'schedule' => ['mode' => 'relative', 'anchor' => 'created_at', 'amount' => 3, 'unit' => 'days'],
        'is_active' => true,
    ], $attributes));
}

function markRunSuccessful(AutomationRule $rule, Order $order): AutomationRuleRun
{
    return AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'main',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'time.relative',
        'status' => AutomationRuleRun::STATUS_SUCCESS,
        'results' => [],
    ]);
}

it('kiest orders met anker tussen horizon en delay, niet daarbuiten', function () {
    $now = Carbon::parse('2026-03-01 10:00');
    $due = scannerOrder('main', $now->copy()->subDays(4));       // > 3d delay, binnen horizon
    $tooNew = scannerOrder('main', $now->copy()->subDays(2));    // < 3d delay, nog niet rijp
    $tooOld = scannerOrder('main', $now->copy()->subDays(120));  // > 90d horizon, te oud

    $ids = TimeRuleScanner::relativeCandidates(relRule(), $now)->pluck('id');

    expect($ids)->toContain($due->id)
        ->not->toContain($tooNew->id)
        ->not->toContain($tooOld->id);
});

it('sluit orders uit die al een geslaagde run hebben (één keer ooit)', function () {
    $now = Carbon::parse('2026-03-01 10:00');
    $rule = relRule();
    $order = scannerOrder('main', $now->copy()->subDays(4));
    markRunSuccessful($rule, $order);

    expect(TimeRuleScanner::relativeCandidates($rule, $now)->pluck('id'))->not->toContain($order->id);
});

it('sluit orders van een andere site uit', function () {
    $now = Carbon::parse('2026-03-01 10:00');
    $other = scannerOrder('other', $now->copy()->subDays(4));

    expect(TimeRuleScanner::relativeCandidates(relRule(), $now)->pluck('id'))->not->toContain($other->id);
});

it('negeert een niet-succesvolle run en levert de order alsnog als kandidaat', function () {
    $now = Carbon::parse('2026-03-01 10:00');
    $rule = relRule();
    $order = scannerOrder('main', $now->copy()->subDays(4));
    AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'main',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'time.relative',
        'status' => AutomationRuleRun::STATUS_FAILED,
        'results' => [],
    ]);

    expect(TimeRuleScanner::relativeCandidates($rule, $now)->pluck('id'))->toContain($order->id);
});

it('geeft een lege collectie en logt bij een ongeldige schedule', function () {
    Log::spy();
    $rule = relRule(['schedule' => ['mode' => 'relative']]);

    $result = TimeRuleScanner::relativeCandidates($rule, Carbon::parse('2026-03-01 10:00'));

    expect($result)->toBeEmpty();
    Log::shouldHaveReceived('info')->once();
});

it('logt het aantal overgeslagen orders wanneer er kandidaten buiten de horizon of al gedraaid zijn', function () {
    Log::spy();
    $now = Carbon::parse('2026-03-01 10:00');
    $rule = relRule();
    scannerOrder('main', $now->copy()->subDays(120)); // buiten horizon

    TimeRuleScanner::relativeCandidates($rule, $now);

    Log::shouldHaveReceived('info')->once();
});
