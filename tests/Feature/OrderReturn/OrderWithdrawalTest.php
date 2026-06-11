<?php

use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Livewire\Frontend\OrderWithdrawal;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRequestedMail;

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
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->set('customerNote', 'Past niet')
        ->call('confirm')
        ->assertSet('completed', true);

    $return = OrderReturn::first();
    expect($return)->not->toBeNull()
        ->and($return->status)->toBe(OrderReturn::STATUS_REQUESTED)
        ->and($return->customer_note)->toBe('Past niet')
        ->and($order->fresh()->retour_status)->toBe('waiting_for_return');

    Mail::assertQueued(OrderReturnRequestedMail::class);
});

it('does not create a second return when an open one exists', function () {
    Mail::fake();
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-1001']);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com']);

    Livewire::test(OrderWithdrawal::class)
        ->set('orderNumber', 'INV-1001')
        ->set('email', 'klant@example.com')
        ->call('search')
        ->call('confirm')
        ->assertSet('completed', true);

    expect(OrderReturn::where('order_id', $order->id)->count())->toBe(1);
});
