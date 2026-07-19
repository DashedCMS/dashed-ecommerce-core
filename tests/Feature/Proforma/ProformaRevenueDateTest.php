<?php

// tests/Feature/Proforma/ProformaRevenueDateTest.php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

it('moves a concept order created_at to the first paid payment', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true, 'total' => 100]);
    $order->created_at = now()->subMonth();
    $order->save();

    $paymentTime = now()->subDays(2);
    $payment = OrderPayment::create(['order_id' => $order->id, 'status' => 'paid', 'amount' => 100]);
    $payment->created_at = $paymentTime;
    $payment->save();

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBe($paymentTime->timestamp);
});

it('picks the oldest paid payment when several exist', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true, 'total' => 100]);
    $order->created_at = now()->subMonth();
    $order->save();

    $first = OrderPayment::create(['order_id' => $order->id, 'status' => 'paid', 'amount' => 40]);
    $first->created_at = now()->subDays(5);
    $first->save();

    $second = OrderPayment::create(['order_id' => $order->id, 'status' => 'paid', 'amount' => 60]);
    $second->created_at = now()->subDays(1);
    $second->save();

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBe(now()->subDays(5)->timestamp);
});

it('falls back to now when a concept order has no paid payment', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true]);
    $order->created_at = now()->subMonth();
    $order->save();

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBeGreaterThanOrEqual(now()->subMinute()->timestamp);
});

it('ignores pending payments when finding the first payment', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => Order::STATUS_CONCEPT, 'is_proforma' => true, 'total' => 100]);
    $order->created_at = now()->subMonth();
    $order->save();

    $pending = OrderPayment::create(['order_id' => $order->id, 'status' => 'pending', 'amount' => 100]);
    $pending->created_at = now()->subDays(10);
    $pending->save();

    $paid = OrderPayment::create(['order_id' => $order->id, 'status' => 'paid', 'amount' => 100]);
    $paid->created_at = now()->subDays(3);
    $paid->save();

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBe(now()->subDays(3)->timestamp);
});

it('does not change created_at for a non-concept order', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'total' => 100]);
    $original = now()->subMonth();
    $order->created_at = $original;
    $order->save();

    OrderPayment::create(['order_id' => $order->id, 'status' => 'paid', 'amount' => 100]);

    $order->alignCreatedAtToFirstPayment();

    expect($order->created_at->timestamp)->toBe($original->timestamp);
});

it('backfills paid proforma orders to their first payment date', function () {
    $legacy = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'is_proforma' => true, 'total' => 100]);
    $legacy->created_at = now()->subMonth();
    $legacy->save();

    $paymentTime = now()->subDays(2);
    $payment = OrderPayment::create(['order_id' => $legacy->id, 'status' => 'paid', 'amount' => 100]);
    $payment->created_at = $paymentTime;
    $payment->save();

    Order::realignProformaCreatedAtToFirstPayment();

    expect($legacy->fresh()->created_at->timestamp)->toBe($paymentTime->timestamp);
});

it('leaves a proforma without a paid payment untouched during backfill', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'waiting_for_confirmation', 'is_proforma' => true, 'total' => 100]);
    $original = now()->subMonth();
    $order->created_at = $original;
    $order->save();

    Order::realignProformaCreatedAtToFirstPayment();

    expect($order->fresh()->created_at->timestamp)->toBe($original->timestamp);
});

it('leaves non-proforma paid orders untouched during backfill', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'is_proforma' => false, 'total' => 100]);
    $original = now()->subMonth();
    $order->created_at = $original;
    $order->save();

    $payment = OrderPayment::create(['order_id' => $order->id, 'status' => 'paid', 'amount' => 100]);
    $payment->created_at = now()->subDays(2);
    $payment->save();

    Order::realignProformaCreatedAtToFirstPayment();

    expect($order->fresh()->created_at->timestamp)->toBe($original->timestamp);
});
