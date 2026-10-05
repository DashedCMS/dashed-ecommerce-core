<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Services\Payments\PaymentFailure;

function maakBetaling(Order $order, array $extra = []): OrderPayment
{
    return OrderPayment::create(array_merge([
        'order_id' => $order->id,
        'psp' => 'paynl',
        'psp_id' => uniqid(),
        'payment_method' => 'Riverty – Achteraf betalen',
        'amount' => 30,
        'status' => 'cancelled',
    ], $extra));
}

it('kijkt naar de laatste betaling van de order', function () {
    $order = Order::create(['email' => 'a@example.test', 'status' => 'cancelled', 'total' => 30]);
    maakBetaling($order, ['attributes' => ['psp_state' => 'DENIED_63']]);
    $laatste = maakBetaling($order, ['attributes' => ['psp_state' => 'CANCEL']]);

    expect(PaymentFailure::lastPayment($order)?->id)->toBe($laatste->id)
        ->and(PaymentFailure::isDeclined($order))->toBeFalse();
});

it('geeft de drie sessiewaarden met de betaalmethode-id bij een afwijzing', function () {
    $methode = new PaymentMethod();
    $methode->site_id = 'site';
    $methode->setTranslations('name', ['nl' => 'Riverty – Achteraf betalen']);
    $methode->type = 'online';
    $methode->psp = 'paynl';
    $methode->psp_id = '2561';
    $methode->active = 1;
    $methode->save();

    $order = Order::create(['email' => 'b@example.test', 'status' => 'cancelled', 'total' => 30]);
    maakBetaling($order, ['payment_method' => null, 'payment_method_id' => $methode->id, 'attributes' => ['psp_state' => 'DENIED_63']]);

    expect(PaymentFailure::sessionData($order))->toBe([
        'cancelled_order_id' => $order->id,
        'payment_declined' => true,
        'declined_payment_method_id' => $methode->id,
    ])->and(PaymentFailure::methodName($order))->toBe('Riverty – Achteraf betalen');
});

it('valt voor de naam terug op de tekstkolom en geeft geen methode-id zonder afwijzing', function () {
    $order = Order::create(['email' => 'c@example.test', 'status' => 'cancelled', 'total' => 30]);
    maakBetaling($order, ['attributes' => ['psp_state' => 'CANCEL']]);

    expect(PaymentFailure::sessionData($order))->toBe([
        'cancelled_order_id' => $order->id,
        'payment_declined' => false,
        'declined_payment_method_id' => null,
    ])->and(PaymentFailure::methodName($order))->toBe('Riverty – Achteraf betalen');
});

it('herkent een afwijzing ook zonder payment_method_id', function () {
    $order = Order::create(['email' => 'd@example.test', 'status' => 'cancelled', 'total' => 30]);
    maakBetaling($order, ['attributes' => ['psp_state' => 'DENIED_63']]);

    expect(PaymentFailure::sessionData($order)['payment_declined'])->toBeTrue()
        ->and(PaymentFailure::sessionData($order)['declined_payment_method_id'])->toBeNull();
});

it('zet de drie sleutels in de sessie-flash', function () {
    $order = Order::create(['email' => 'e@example.test', 'status' => 'cancelled', 'total' => 30]);
    maakBetaling($order, ['attributes' => ['psp_state' => 'DENIED_63']]);

    PaymentFailure::flash($order);

    expect(session()->get('cancelled_order_id'))->toBe($order->id)
        ->and(session()->get('payment_declined'))->toBeTrue()
        ->and(session()->get('declined_payment_method_id'))->toBeNull();
});
