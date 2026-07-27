<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

/**
 * Task 6 (B2): RunTimeBasedAutomationRules bindt de eerdere bouwstenen samen
 * — TimeRuleScanner (kandidaten), ConditionEvaluator (voorwaarden op
 * vuurmoment) en AutomationEngine (uitvoering + claim-before-execute). Er is
 * geen Order::factory() in dit package (zie TimeRuleScannerRelativeTest), dus
 * orders worden met Order::create() + forceFill()/save() opgebouwd, net als
 * daar.
 */
function runTimeAutomationsOrder(string $siteId, Carbon $createdAt, array $extra = []): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
    ]);
    $order->forceFill(array_merge(['site_id' => $siteId, 'created_at' => $createdAt], $extra))->save();

    return $order->fresh();
}

it('draait een verschuldigde relatieve regel één keer via de engine', function () {
    Carbon::setTestNow('2026-03-01 10:00');

    $order = runTimeAutomationsOrder('main', Carbon::parse('2026-02-25')); // 4d oud

    AutomationRule::create([
        'site_id' => 'main',
        'name' => 'R',
        'trigger' => 'time.relative',
        'conditions' => [], // lege voorwaarden = altijd raak
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'schedule' => ['mode' => 'relative', 'anchor' => 'created_at', 'amount' => 3, 'unit' => 'days'],
        'is_active' => true,
    ]);

    Artisan::call('dashed:run-time-automations');
    expect(AutomationRuleRun::where('subject_id', $order->id)->where('status', 'success')->count())->toBe(1);

    // tweede keer: geen nieuwe run (één keer ooit, want relativeCandidates
    // sluit orders met een geslaagde run al uit).
    Artisan::call('dashed:run-time-automations');
    expect(AutomationRuleRun::where('subject_id', $order->id)->count())->toBe(1);

    Carbon::setTestNow();
});

it('vuurt niet als de voorwaarde op vuurmoment niet matcht', function () {
    Carbon::setTestNow('2026-03-01 10:00');

    $order = runTimeAutomationsOrder('main', Carbon::parse('2026-02-25'), ['fulfillment_status' => 'handled']);

    AutomationRule::create([
        'site_id' => 'main',
        'name' => 'R',
        'trigger' => 'time.relative',
        // ConditionEvaluator::matches() kent 'neq' (niet 'not_equals') als
        // "niet gelijk aan"-operator — geverifieerd tegen
        // src/Support/Automation/ConditionEvaluator.php.
        'conditions' => [['field' => 'fulfillment_status', 'operator' => 'neq', 'value' => 'handled']],
        'actions' => [['key' => 'mark_packed', 'params' => []]],
        'schedule' => ['mode' => 'relative', 'anchor' => 'created_at', 'amount' => 3, 'unit' => 'days'],
        'is_active' => true,
    ]);

    Artisan::call('dashed:run-time-automations');
    expect(AutomationRuleRun::where('subject_id', $order->id)->count())->toBe(0);

    Carbon::setTestNow();
});
