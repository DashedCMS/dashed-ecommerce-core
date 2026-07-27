<?php

declare(strict_types=1);

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Support\CustomerAutomationTriggers;

/**
 * Task 4 (B3): customer.new/customer.nth_order. Beide vuren op
 * OrderCreatedEvent met de Order als onderwerp, maar de conditie-context komt
 * van AutomationContext::forCustomer() (order_count/total_spend/email/
 * is_registered) i.p.v. de gewone order-velden — dat is wat de
 * `context => 'customer'`-marker in AutomationTriggerSubscriber::handle()
 * regelt (zie CustomerAutomationTriggers). Order::create() dispatcht
 * OrderCreatedEvent niet zelf (dat gebeurt in Livewire\Checkout), dus net als
 * AutomationEngineTest vuren we het event hier expliciet.
 */
function customerTriggerSite(): string
{
    return Sites::getActive();
}

function makeCustomerTriggerOrder(array $attributes = []): Order
{
    return Order::create(array_merge([
        'email' => 'klant@example.com',
        'status' => 'concept',
    ], $attributes));
}

function makeCustomerTriggerRule(string $trigger, array $conditions, array $attributes = []): AutomationRule
{
    return AutomationRule::create(array_merge([
        'site_id' => customerTriggerSite(),
        'name' => 'Klant-regel',
        'trigger' => $trigger,
        'conditions' => $conditions,
        'actions' => [],
        'is_active' => true,
    ], $attributes));
}

beforeEach(function () {
    CustomerAutomationTriggers::register(app(MobileApiRegistry::class));
});

it('registreert customer.new en customer.nth_order met de klant-conditievelden en context-marker', function () {
    $registry = app(MobileApiRegistry::class);

    $new = $registry->automationTrigger('customer.new');
    $nth = $registry->automationTrigger('customer.nth_order');

    expect($new)->not->toBeNull()
        ->and($new['subject'])->toBe('customer')
        ->and($new['context'])->toBe('customer')
        ->and($new['event'])->toBe(OrderCreatedEvent::class)
        ->and(collect($new['fields'])->pluck('name')->all())->toBe(['order_count', 'total_spend', 'email', 'is_registered']);

    expect($nth)->not->toBeNull()
        ->and($nth['subject'])->toBe('customer')
        ->and($nth['context'])->toBe('customer')
        ->and($nth['event'])->toBe(OrderCreatedEvent::class);
});

it('customer.new vuurt op de eerste order van een klant (order_count eq 1)', function () {
    $rule = makeCustomerTriggerRule('customer.new', [
        ['field' => 'order_count', 'operator' => 'eq', 'value' => 1],
    ]);

    $order = makeCustomerTriggerOrder(['email' => 'nieuw@example.com']);
    event(new OrderCreatedEvent($order));

    $runs = AutomationRuleRun::where('rule_id', $rule->id)->get();
    expect($runs)->toHaveCount(1)
        ->and($runs->first()->status)->toBe(AutomationRuleRun::STATUS_SUCCESS);
});

it('customer.new vuurt NIET meer op de tweede order van dezelfde klant (order_count 2)', function () {
    $rule = makeCustomerTriggerRule('customer.new', [
        ['field' => 'order_count', 'operator' => 'eq', 'value' => 1],
    ]);

    $first = makeCustomerTriggerOrder(['email' => 'terugkerend@example.com']);
    event(new OrderCreatedEvent($first));
    expect(AutomationRuleRun::where('rule_id', $rule->id)->count())->toBe(1);

    $second = makeCustomerTriggerOrder(['email' => 'terugkerend@example.com']);
    event(new OrderCreatedEvent($second));

    // Nog steeds maar 1 run: de tweede order heeft order_count 2, dus de
    // conditie order_count eq 1 matcht niet meer.
    expect(AutomationRuleRun::where('rule_id', $rule->id)->count())->toBe(1);
});

it('customer.nth_order vuurt op de N-de bestelling via order_count eq N', function () {
    $rule = makeCustomerTriggerRule('customer.nth_order', [
        ['field' => 'order_count', 'operator' => 'eq', 'value' => 2],
    ]);

    $first = makeCustomerTriggerOrder(['email' => 'trouw@example.com']);
    event(new OrderCreatedEvent($first));
    expect(AutomationRuleRun::where('rule_id', $rule->id)->count())->toBe(0);

    $second = makeCustomerTriggerOrder(['email' => 'trouw@example.com']);
    event(new OrderCreatedEvent($second));

    $runs = AutomationRuleRun::where('rule_id', $rule->id)->get();
    expect($runs)->toHaveCount(1)
        ->and($runs->first()->status)->toBe(AutomationRuleRun::STATUS_SUCCESS)
        ->and($runs->first()->subject_id)->toBe($second->id);
});
