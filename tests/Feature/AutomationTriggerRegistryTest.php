<?php

use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnRequestedEvent;
use Dashed\DashedEcommerceCore\Livewire\Frontend\OrderWithdrawal;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

/**
 * Sinds B2 zitten er naast de zes order-triggers ook twee tijd-triggers
 * (`time.relative`/`time.recurring`, type => 'time') in de registry — die
 * vuren via de uurlijkse scan, niet via een event, en hebben dus bewust
 * geen 'event'/'resolve'. Deze test gaat alleen over de order-triggers:
 * hij filtert tijd-triggers eruit vóórdat hij de "zes order-triggers hebben
 * subject+event+resolve"-assertie doet, zodat die intentie (order-triggers
 * zijn event-gebaseerd) onverzwakt getest blijft. Het aparte, bedoelde
 * gedrag van de tijd-triggers staat in de test hieronder.
 */
it('registers the six order automation triggers with a subject, event class and resolve callable', function () {
    $registry = app(MobileApiRegistry::class);
    $orderTriggers = collect($registry->automationTriggers())
        ->reject(fn (array $trigger): bool => ($trigger['type'] ?? null) === 'time')
        ->keyBy('key');

    $expectedKeys = [
        'order.created',
        'order.paid',
        'order.cancelled',
        'order.fulfillment_changed',
        'order.return_requested',
        'order.return_approved',
    ];

    expect($orderTriggers->keys()->all())->toEqualCanonicalizing($expectedKeys);

    foreach ($expectedKeys as $key) {
        $trigger = $orderTriggers[$key];
        expect($trigger['subject'])->toBe('order')
            ->and($trigger['event'])->toBeString()
            ->and(class_exists($trigger['event']))->toBeTrue("event class ontbreekt voor {$key}: {$trigger['event']}")
            ->and($trigger['resolve'])->toBeCallable();
    }
});

/**
 * B2: time.relative/time.recurring zijn scan-gebaseerd (RunTimeBasedAutomation
 * Rules / TimeRuleScanner leest AutomationRule::schedule en vuurt op basis
 * daarvan), niet event-gebaseerd — er is dus bewust GEEN 'event'-class en
 * GEEN 'resolve'-callable, in tegenstelling tot de order-triggers hierboven.
 * Dit legt die bedoelde registry-vorm vast, los van de order-trigger-test.
 */
it('registers time.relative and time.recurring as scan-based triggers without an event/resolve callable', function () {
    $byKey = collect(app(MobileApiRegistry::class)->automationTriggers())->keyBy('key');

    foreach (['time.relative', 'time.recurring'] as $key) {
        expect($byKey->has($key))->toBeTrue("tijd-trigger {$key} ontbreekt in de registry");

        $trigger = $byKey[$key];
        expect($trigger['type'])->toBe('time')
            ->and($trigger)->not->toHaveKey('event')
            ->and($trigger)->not->toHaveKey('resolve');
    }
});

it('exposes old_status and new_status fields on order.fulfillment_changed', function () {
    $trigger = app(MobileApiRegistry::class)->automationTrigger('order.fulfillment_changed');
    $fieldNames = collect($trigger['fields'])->pluck('name')->all();

    expect($fieldNames)->toContain('old_status')
        ->and($fieldNames)->toContain('new_status');
});

it('returns the descriptor for a single trigger key via automationTrigger()', function () {
    $trigger = app(MobileApiRegistry::class)->automationTrigger('order.paid');

    expect($trigger)->not->toBeNull()
        ->and($trigger['key'])->toBe('order.paid')
        ->and($trigger['event'])->toBe(OrderMarkedAsPaidEvent::class);

    expect(app(MobileApiRegistry::class)->automationTrigger('order.does-not-exist'))->toBeNull();
});

it('resolves the order from each trigger event via its resolve callable', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-2001']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => $order->email]);

    $registry = app(MobileApiRegistry::class);

    $cases = [
        'order.created' => new OrderCreatedEvent($order),
        'order.paid' => new OrderMarkedAsPaidEvent($order),
        'order.cancelled' => new OrderCancelledEvent($order),
        'order.fulfillment_changed' => new OrderFulfillmentStatusChangedEvent($order, 'unhandled', 'packed'),
        'order.return_requested' => new OrderReturnRequestedEvent($return),
        'order.return_approved' => new OrderReturnApprovedEvent($return),
    ];

    foreach ($cases as $key => $event) {
        $trigger = $registry->automationTrigger($key);
        $resolved = ($trigger['resolve'])($event);
        expect($resolved)->toBeInstanceOf(Order::class)
            ->and($resolved->id)->toBe($order->id);
    }
});

it('dispatches OrderReturnRequestedEvent when a return is created via the withdrawal flow', function () {
    Event::fake([OrderReturnRequestedEvent::class]);

    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-3001']);
    $product = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 1, 'price' => 20]);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-3001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set("selectedLines.{$product->id}.selected", true)
        ->call('confirm')
        ->assertSet('completed', true);

    $return = OrderReturn::where('order_id', $order->id)->firstOrFail();

    Event::assertDispatched(OrderReturnRequestedEvent::class, function ($event) use ($return) {
        return $event->orderReturn->is($return);
    });
});
