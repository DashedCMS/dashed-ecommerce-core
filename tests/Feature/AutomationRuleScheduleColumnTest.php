<?php

use Dashed\DashedEcommerceCore\Models\AutomationRule;

it('slaat een schedule-array op en leest die terug als array', function () {
    $rule = AutomationRule::create([
        'site_id' => 'main', 'name' => 'T', 'trigger' => 'time.relative',
        'conditions' => [], 'actions' => [],
        'schedule' => ['mode' => 'relative', 'anchor' => 'created_at', 'amount' => 3, 'unit' => 'days'],
        'is_active' => true,
    ]);
    expect(AutomationRule::find($rule->id)->schedule)->toBe([
        'mode' => 'relative', 'anchor' => 'created_at', 'amount' => 3, 'unit' => 'days',
    ]);
});

it('laat schedule null zijn voor een event-regel', function () {
    $rule = AutomationRule::create([
        'site_id' => 'main', 'name' => 'E', 'trigger' => 'order.paid',
        'conditions' => [], 'actions' => [], 'is_active' => true,
    ]);
    expect(AutomationRule::find($rule->id)->schedule)->toBeNull();
});
