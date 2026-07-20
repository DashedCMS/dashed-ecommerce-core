<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

it('round-trips conditions and actions as arrays', function () {
    $rule = AutomationRule::create([
        'site_id' => 'main',
        'name' => 'Stuur bevestiging bij hoge orderwaarde',
        'trigger' => 'order.paid',
        'conditions' => [
            ['field' => 'total', 'operator' => '>', 'value' => 100],
        ],
        'actions' => [
            ['key' => 'send_mail', 'params' => ['template' => 'high-value-order']],
        ],
        'is_active' => true,
    ]);

    $rule->refresh();

    expect($rule->conditions)->toBe([
        ['field' => 'total', 'operator' => '>', 'value' => 100],
    ])
        ->and($rule->actions)->toBe([
            ['key' => 'send_mail', 'params' => ['template' => 'high-value-order']],
        ])
        ->and($rule->is_active)->toBeTrue();
});

it('active scope filters on is_active', function () {
    AutomationRule::create([
        'site_id' => 'main',
        'name' => 'Actieve regel',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
    ]);

    AutomationRule::create([
        'site_id' => 'main',
        'name' => 'Inactieve regel',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [],
        'is_active' => false,
    ]);

    $active = AutomationRule::active()->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->name)->toBe('Actieve regel');
});

it('forTrigger scope filters on trigger key', function () {
    AutomationRule::create([
        'site_id' => 'main',
        'name' => 'Bij betaling',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
    ]);

    AutomationRule::create([
        'site_id' => 'main',
        'name' => 'Bij annulering',
        'trigger' => 'order.cancelled',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
    ]);

    $matches = AutomationRule::forTrigger('order.paid')->get();

    expect($matches)->toHaveCount(1)
        ->and($matches->first()->trigger)->toBe('order.paid');
});

it('couples a run to its rule and to an order as subject', function () {
    $rule = AutomationRule::create([
        'site_id' => 'main',
        'name' => 'Bij betaling',
        'trigger' => 'order.paid',
        'conditions' => [],
        'actions' => [['key' => 'send_mail', 'params' => []]],
        'is_active' => true,
    ]);

    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid']);

    $run = AutomationRuleRun::create([
        'rule_id' => $rule->id,
        'site_id' => 'main',
        'subject_type' => Order::class,
        'subject_id' => $order->id,
        'trigger' => 'order.paid',
        'status' => 'success',
        'results' => ['send_mail' => 'sent'],
        'error' => null,
    ]);

    $run->refresh();

    expect($run->rule->is($rule))->toBeTrue()
        ->and($run->subject->is($order))->toBeTrue()
        ->and($run->results)->toBe(['send_mail' => 'sent'])
        ->and($run->status)->toBe('success');

    expect($rule->runs)->toHaveCount(1)
        ->and($rule->runs->first()->is($run))->toBeTrue();
});
