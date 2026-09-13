<?php

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Services\OrderReturn\RefundRegistrar;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRefundedMail;

beforeEach(fn () => Mail::fake());

function refundTestReturn(array $orderAttrs = [], string $status = OrderReturn::STATUS_HANDLED, bool $withCredit = true): OrderReturn
{
    ['order' => $order] = spilOrder($orderAttrs);
    $credit = $withCredit
        ? Order::create(['email' => 'klant@example.com', 'status' => 'return', 'credit_for_order_id' => $order->id, 'total' => -40, 'invoice_id' => 'CR-' . uniqid(), 'order_origin' => $order->order_origin])
        : null;

    return OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'status' => $status, 'credit_order_id' => $credit?->id])->fresh();
}

it('boekt een negatieve betaalde betaling op de creditorder en mailt de klant', function () {
    $return = refundTestReturn();

    $payment = app(RefundRegistrar::class)->register($return, 40, 'Bankoverschrijving');

    $credit = $return->creditOrder->fresh();
    expect((float) $payment->amount)->toBe(-40.0)
        ->and($payment->status)->toBe('paid')
        ->and($payment->psp)->toBe('own')
        ->and($payment->payment_method)->toBe('Bankoverschrijving')
        ->and($payment->attributes['refund'])->toBeTrue()
        ->and($payment->order_id)->toBe($credit->id)
        ->and($credit->status)->toBe('return')
        ->and($return->fresh()->isRefunded())->toBeTrue()
        ->and(OrderLog::where('order_id', $return->order_id)->where('tag', 'order.return-refunded')->exists())->toBeTrue()
        ->and(OrderLog::where('order_id', $credit->id)->where('tag', 'order.return-refunded')->exists())->toBeTrue();

    Mail::assertQueued(OrderReturnRefundedMail::class, fn ($m) => $m->orderReturn->is($return) && $m->hasTo('klant@example.com'));
});

it('neemt psp, psp_id en extra attributen mee voor een PSP-terugbetaling', function () {
    $return = refundTestReturn();

    $payment = app(RefundRegistrar::class)->register($return, 40, 'iDEAL', 'paynl', ['psp_id' => 'TX-1', 'paynl_refund_id' => 'RF-1']);

    expect($payment->psp)->toBe('paynl')
        ->and($payment->psp_id)->toBe('TX-1')
        ->and($payment->attributes['paynl_refund_id'])->toBe('RF-1');
});

it('weigert dubbel, nul, te hoog, zonder creditorder en in een verkeerde status', function () {
    $registrar = app(RefundRegistrar::class);

    $return = refundTestReturn();
    $registrar->register($return, 40, 'Contant');
    expect(fn () => $registrar->register($return->fresh(), 40, 'Contant'))->toThrow(InvalidArgumentException::class);

    $vers = refundTestReturn();
    expect(fn () => $registrar->register($vers, 0, 'Contant'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registrar->register($vers, 40.01, 'Contant'))->toThrow(InvalidArgumentException::class);
    expect($vers->creditOrder->orderPayments()->count())->toBe(0);

    $zonder = refundTestReturn(withCredit: false);
    expect(fn () => $registrar->register($zonder, 10, 'Contant'))->toThrow(InvalidArgumentException::class);

    $open = refundTestReturn(status: OrderReturn::STATUS_APPROVED);
    expect(fn () => $registrar->register($open, 10, 'Contant'))->toThrow(InvalidArgumentException::class);
});

it('mailt een Bol-klant niet', function () {
    $return = refundTestReturn(['order_origin' => 'Bol']);

    app(RefundRegistrar::class)->register($return, 40, 'Bankoverschrijving');

    Mail::assertNotQueued(OrderReturnRefundedMail::class);
    expect(OrderLog::where('order_id', $return->order_id)->where('tag', 'order.return-mail-skipped-bol')->exists())->toBeTrue();
});
