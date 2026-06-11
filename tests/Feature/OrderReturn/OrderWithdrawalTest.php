<?php

use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Livewire\Frontend\OrderWithdrawal;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRequestedMail;
use Dashed\DashedEcommerceCore\Mail\AdminNewOrderReturnMail;

it('finds an order and shows the confirm step', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->assertSet('foundOrderId', $order->id)
        ->assertSet('step', 2);
});

it('shows a neutral error when not found', function () {
    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'NOPE')
        ->set('email', 'x@y.nl')
        ->call('search')
        ->assertSet('step', 1)
        ->assertSet('notFound', true);
});

it('creates a return request and mails the customer on confirm', function () {
    Mail::fake();
    \Dashed\DashedCore\Models\Customsetting::set('notification_invoice_emails', ['beheerder@example.com']);
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);
    $product = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 1, 'price' => 20]);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set('customerNote', 'Past niet')
        ->set("selectedLines.{$product->id}.selected", true)
        ->call('confirm')
        ->assertSet('completed', true)
        ->assertSet('completedOrderLabel', 'INV-1001');

    $return = OrderReturn::first();
    expect($return)->not->toBeNull()
        ->and($return->status)->toBe(OrderReturn::STATUS_REQUESTED)
        ->and($return->customer_note)->toBe('Past niet')
        ->and($order->fresh()->retour_status)->toBe('waiting_for_return');

    Mail::assertQueued(OrderReturnRequestedMail::class);
    Mail::assertSent(AdminNewOrderReturnMail::class);
});

it('does not create a second return when an open one exists', function () {
    Mail::fake();
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);
    $product = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 1, 'price' => 20]);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com']);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set("selectedLines.{$product->id}.selected", true)
        ->call('confirm')
        ->assertSet('completed', true);

    expect(OrderReturn::where('order_id', $order->id)->count())->toBe(1);
});

it('rejects a confirm with a foundOrderId that does not match the supplied credentials', function () {
    Mail::fake();
    $mine = Order::create(['email' => 'me@example.com', 'status' => 'paid', 'invoice_id' => 'MINE-1']);
    $victim = Order::create(['email' => 'victim@example.com', 'status' => 'paid', 'invoice_id' => 'VICT-1']);

    // Attacker supplies their own credentials but points foundOrderId at the victim order.
    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'MINE-1')
        ->set('email', 'me@example.com')
        ->set('foundOrderId', $victim->id)
        ->call('confirm')
        ->assertSet('completed', false);

    expect(OrderReturn::where('order_id', $victim->id)->count())->toBe(0);
});

it('blocks search after too many attempts', function () {
    RateLimiter::clear('order-withdrawal:127.0.0.1');

    $component = Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'NOPE')
        ->set('email', 'x@y.nl');

    // 5 allowed attempts proceed to the lookup (notFound), rate limit not yet tripped.
    for ($i = 0; $i < 5; $i++) {
        $component->call('search')->assertSet('rateLimitMessage', null);
    }

    // 6th attempt is throttled.
    $component->call('search');
    expect($component->get('rateLimitMessage'))->not->toBeNull();
});

it('rejects a confirm when credentials match no order at all', function () {
    Mail::fake();
    $victim = Order::create(['email' => 'victim@example.com', 'status' => 'paid', 'invoice_id' => 'VICT-2']);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'GUESS')
        ->set('email', 'attacker@example.com')
        ->set('foundOrderId', $victim->id)
        ->call('confirm')
        ->assertSet('completed', false);

    expect(OrderReturn::where('order_id', $victim->id)->count())->toBe(0);
});
