<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRejectedMail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

it('creates an order return with a hash, requested status and requested_at', function () {
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
    ]);

    $return = OrderReturn::create([
        'order_id' => $order->id,
        'email' => 'klant@example.com',
    ]);

    expect($return->hash)->toBeString()->toHaveLength(32)
        ->and($return->status)->toBe(OrderReturn::STATUS_REQUESTED)
        ->and($return->requested_at)->not->toBeNull()
        ->and($return->order->is($order))->toBeTrue();
});

it('exposes scopes for open and requested returns', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $requested = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    $handled = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_HANDLED]);

    expect(OrderReturn::requested()->pluck('id')->all())->toBe([$requested->id])
        ->and(OrderReturn::open()->pluck('id')->all())->toContain($requested->id)
        ->and(OrderReturn::open()->pluck('id')->all())->not->toContain($handled->id);
});

it('approves a return and mirrors retour_status', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->approve('Akkoord, stuur maar terug');

    expect($return->fresh()->status)->toBe(OrderReturn::STATUS_APPROVED)
        ->and($return->fresh()->approved_at)->not->toBeNull()
        ->and($return->fresh()->admin_note)->toBe('Akkoord, stuur maar terug')
        ->and(OrderLog::where('order_id', $order->id)->where('tag', 'order.return-approved')->exists())->toBeTrue();
});

it('rejects a return with a reason', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->reject('Buiten de termijn');

    expect($return->fresh()->status)->toBe(OrderReturn::STATUS_REJECTED)
        ->and($return->fresh()->rejected_at)->not->toBeNull()
        ->and($return->fresh()->rejected_reason)->toBe('Buiten de termijn');
});

it('marks a return as handled', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->markHandled();

    expect($return->fresh()->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($return->fresh()->handled_at)->not->toBeNull();
});

it('mails the customer on approve', function () {
    Mail::fake();
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->approve();

    Mail::assertQueued(OrderReturnApprovedMail::class);
});

it('mails the customer on reject', function () {
    Mail::fake();
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->reject('Buiten termijn');

    Mail::assertQueued(OrderReturnRejectedMail::class);
});

it('fires OrderReturnApprovedEvent on approve', function () {
    Event::fake([OrderReturnApprovedEvent::class]);
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $return->approve();

    Event::assertDispatched(OrderReturnApprovedEvent::class, fn ($e) => $e->orderReturn->is($return));
});

it('casts auto_accepted and stores label fields', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create([
        'order_id' => $order->id,
        'email' => 'a@b.nl',
        'auto_accepted' => true,
        'return_label_provider' => 'myparcel',
        'return_label_path' => '/labels/x.pdf',
    ]);

    $fresh = OrderReturn::find($return->id);
    expect($fresh->auto_accepted)->toBeTrue()
        ->and($fresh->return_label_provider)->toBe('myparcel')
        ->and($fresh->return_label_path)->toBe('/labels/x.pdf');
});
