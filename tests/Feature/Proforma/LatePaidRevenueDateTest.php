<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

function latePaidOrder(string $status, $createdAt): Order
{
    $order = Order::create(['email' => 'a@b.nl', 'status' => $status, 'total' => 100]);
    $order->created_at = $createdAt;
    $order->save();

    return $order;
}

function revenueLog(Order $order, string $tag, $at): void
{
    $log = OrderLog::create(['order_id' => $order->id, 'tag' => $tag]);
    $log->created_at = $at;
    $log->save();
}

it('moves a pending order paid on a later day to the moment of payment', function () {
    $order = latePaidOrder('pending', now()->subDays(10));

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBeGreaterThanOrEqual(now()->subMinute()->timestamp);
});

it('moves a cancelled order that is paid after all to the moment of payment', function () {
    $order = latePaidOrder('cancelled', now()->subDays(3));

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->isToday())->toBeTrue();
});

it('leaves a pending order paid on the same day untouched', function () {
    $original = now()->startOfDay()->addMinute();
    $order = latePaidOrder('pending', $original);

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBe($original->timestamp);
});

it('does not move an order from waiting_for_confirmation or partially_paid to paid', function (string $status) {
    $original = now()->subMonth();
    $order = latePaidOrder($status, $original);

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBe($original->timestamp);
})->with(['waiting_for_confirmation', 'partially_paid']);

it('backfills a late paid order to its first revenue log', function () {
    $order = latePaidOrder('paid', now()->subDays(20));
    $paidAt = now()->subDays(12);
    revenueLog($order, 'order.paid', $paidAt);
    revenueLog($order, 'order.marked-as-paid', now()->subDays(5));

    Order::realignLatePaidCreatedAtToPaymentDate();

    expect($order->fresh()->created_at->timestamp)->toBe($paidAt->timestamp);
});

it('backfills from the first of partially_paid and waiting_for_confirmation logs', function () {
    $order = latePaidOrder('paid', now()->subDays(20));
    $firstAt = now()->subDays(15);
    revenueLog($order, 'order.waiting_for_confirmation', $firstAt);
    revenueLog($order, 'order.marked-as-paid', now()->subDays(2));

    Order::realignLatePaidCreatedAtToPaymentDate();

    expect($order->fresh()->created_at->timestamp)->toBe($firstAt->timestamp);
});

it('leaves an order paid on its creation day untouched during backfill', function () {
    $original = now()->subDays(20)->setTime(10, 0);
    $order = latePaidOrder('paid', $original);
    revenueLog($order, 'order.paid', $original->copy()->addHours(3));

    Order::realignLatePaidCreatedAtToPaymentDate();

    expect($order->fresh()->created_at->timestamp)->toBe($original->timestamp);
});

it('leaves unpaid orders, proformas and replacement orders untouched during backfill', function () {
    $original = now()->subDays(20);

    $pending = latePaidOrder('pending', $original);
    revenueLog($pending, 'order.paid', now()->subDays(2));

    $proforma = latePaidOrder('paid', $original);
    $proforma->forceFill(['is_proforma' => true])->save();
    revenueLog($proforma, 'order.paid', now()->subDays(2));

    $replacement = latePaidOrder('paid', $original);
    revenueLog($replacement, 'order.paid', now()->subDays(2));
    $old = latePaidOrder('cancelled', $original);
    $old->forceFill(['replaced_by_order_id' => $replacement->id])->save();

    Order::realignLatePaidCreatedAtToPaymentDate();

    foreach ([$pending, $proforma, $replacement] as $order) {
        expect($order->fresh()->created_at->timestamp)->toBe($original->timestamp);
    }
});

it('leaves orders without a revenue log untouched during backfill', function () {
    $original = now()->subDays(20);
    $order = latePaidOrder('paid', $original);

    Order::realignLatePaidCreatedAtToPaymentDate();

    expect($order->fresh()->created_at->timestamp)->toBe($original->timestamp);
});

it('moves created_at when a pending order is marked as paid days later', function () {
    $order = latePaidOrder('pending', now()->subDays(10));
    \Illuminate\Support\Facades\Bus::fake();
    \Illuminate\Support\Facades\Event::fake();


    $order->changeStatus('paid');

    expect($order->fresh()->created_at->isToday())->toBeTrue();
});

it('keeps created_at when an order on account is paid later', function () {
    $original = now()->subDays(10);
    $order = latePaidOrder('waiting_for_confirmation', $original);
    \Illuminate\Support\Facades\Bus::fake();
    \Illuminate\Support\Facades\Event::fake();


    $order->changeStatus('paid');

    expect($order->fresh()->created_at->timestamp)->toBe($original->timestamp);
});
