<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

function maakGeannuleerdeOrder(string $staat): Order
{
    $order = Order::create(['email' => 'r@example.test', 'status' => 'cancelled', 'total' => 30]);
    OrderPayment::create([
        'order_id' => $order->id,
        'psp' => 'paynl',
        'psp_id' => uniqid(),
        'payment_method' => 'Riverty – Achteraf betalen',
        'amount' => 30,
        'status' => 'cancelled',
        'attributes' => ['psp_state' => $staat],
    ]);

    return $order;
}

it('geeft bij een afwijzing de reden door en zet geen algemene foutmelding', function () {
    $order = maakGeannuleerdeOrder('DENIED_63');

    $response = ShoppingCart::cancelledPaymentRedirect($order);

    expect($response->getTargetUrl())->toBe(url('/'))
        ->and(session()->get('cancelled_order_id'))->toBe($order->id)
        ->and(session()->get('payment_declined'))->toBeTrue()
        ->and(session()->has('error'))->toBeFalse();
});

it('houdt bij een afgebroken betaling de algemene foutmelding en geeft de order door', function () {
    $order = maakGeannuleerdeOrder('CANCEL');

    ShoppingCart::cancelledPaymentRedirect($order);

    expect(session()->get('cancelled_order_id'))->toBe($order->id)
        ->and(session()->get('payment_declined'))->toBeFalse()
        ->and(session()->has('error'))->toBeTrue();
});

it('werkt zonder order zoals voorheen', function () {
    ShoppingCart::cancelledPaymentRedirect();

    expect(session()->has('error'))->toBeTrue()
        ->and(session()->has('cancelled_order_id'))->toBeFalse();
});
