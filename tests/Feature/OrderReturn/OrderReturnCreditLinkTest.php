<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;

function creditLinkOrder(): Order
{
    return Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-CL-1']);
}

it('koppelt een creditorder aan de retour en terug', function () {
    $order = creditLinkOrder();
    $credit = Order::create(['email' => 'a@b.nl', 'status' => 'return', 'credit_for_order_id' => $order->id, 'total' => -40]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_HANDLED, 'credit_order_id' => $credit->id]);

    expect($return->creditOrder->is($credit))->toBeTrue()
        ->and($order->orderReturns()->pluck('id')->all())->toBe([$return->id])
        ->and($credit->originReturn->is($return))->toBeTrue()
        ->and($return->creditedAmount())->toBe(40.0);
});

it('leidt terugbetaald af uit een betaalde betaling op de creditorder', function () {
    $order = creditLinkOrder();
    $credit = Order::create(['email' => 'a@b.nl', 'status' => 'return', 'credit_for_order_id' => $order->id, 'total' => -40]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_HANDLED, 'credit_order_id' => $credit->id]);

    expect($return->isRefunded())->toBeFalse();

    $credit->orderPayments()->create(['status' => 'paid', 'amount' => -40, 'psp' => 'own', 'payment_method' => 'Bankoverschrijving']);

    expect($return->fresh()->isRefunded())->toBeTrue()
        ->and($return->fresh()->refundPayment()->payment_method)->toBe('Bankoverschrijving');
});

it('kent de status gesloten en houdt hem buiten open en notHandled', function () {
    $order = creditLinkOrder();
    $closed = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_CLOSED]);
    $approved = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_APPROVED]);

    expect(OrderReturn::open()->pluck('id')->all())->toBe([$approved->id])
        ->and(OrderReturn::notHandled()->pluck('id')->all())->toBe([$approved->id])
        ->and(OrderReturn::statusLabels()[OrderReturn::STATUS_CLOSED])->toBe('Gesloten')
        ->and(OrderReturn::statusLabels()[OrderReturn::STATUS_HANDLED])->toBe('Verwerkt');
});

it('cast processed_quantity op een regel naar een geheel getal', function () {
    $order = creditLinkOrder();
    $op = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 3, 'price' => 60]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    $line = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $op->id, 'quantity' => 2, 'processed_quantity' => '1']);

    expect($line->fresh()->processed_quantity)->toBe(1);
});

it('kent de retourstatussen returned en partially_returned', function () {
    expect(Orders::getReturnStatusses())->toHaveKeys(['returned', 'partially_returned']);

    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'retour_status' => 'partially_returned']);

    expect($order->retourStatus())->toBe('Deels geretourneerd');
});
