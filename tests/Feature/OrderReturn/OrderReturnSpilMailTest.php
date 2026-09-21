<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRefundedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnProcessedMail;

function spilMailReturn(): OrderReturn
{
    ['order' => $order, 'shirt' => $shirt] = spilOrder();
    $credit = Order::create(['email' => 'klant@example.com', 'status' => 'return', 'credit_for_order_id' => $order->id, 'total' => -40, 'invoice_id' => 'CR-1', 'hash' => 'h1']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_HANDLED, 'credit_order_id' => $credit->id]);
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $shirt->id, 'quantity' => 3, 'processed_quantity' => 2]);

    return $return->fresh()->load(['order', 'lines.orderProduct', 'creditOrder']);
}

function spilMailReplace(object $mail, string $tekst): string
{
    $m = new ReflectionMethod($mail, 'replaceReturnVariables');
    $m->setAccessible(true);

    return $m->invoke($mail, $tekst, false);
}

it('heeft templatenamen en de nieuwe variabelen', function () {
    expect(OrderReturnProcessedMail::emailTemplateName())->toBe('Retour: verwerkt')
        ->and(OrderReturnRefundedMail::emailTemplateName())->toBe('Retour: terugbetaald')
        ->and(OrderReturnProcessedMail::availableVariables())->toContain('creditedLines', 'creditAmount', 'refundDays', 'refundAmount', 'refundMethod');
});

it('vult creditedLines, creditAmount en refundDays met de verwerkte aantallen', function () {
    Customsetting::set('returns_refund_days', 10);
    $return = spilMailReturn();

    $uit = spilMailReplace(new OrderReturnProcessedMail($return), ':creditedLines: | :creditAmount: | :refundDays:');

    expect($uit)->toContain('2x Shirt')
        ->and($uit)->not->toContain('3x Shirt')
        ->and($uit)->toContain('40')
        ->and($uit)->toContain('| 10');
});

it('vult refundAmount en refundMethod uit de betaling op de creditorder', function () {
    $return = spilMailReturn();
    $return->creditOrder->orderPayments()->create(['status' => 'paid', 'amount' => -40, 'psp' => 'own', 'payment_method' => 'Bankoverschrijving']);

    $uit = spilMailReplace(new OrderReturnRefundedMail($return->fresh()), ':refundAmount: / :refundMethod:');

    expect($uit)->toContain('40')->and($uit)->toContain('Bankoverschrijving');
});

it('hangt de creditfactuur aan de verwerkt-mail als die bestaat', function () {
    Storage::fake('dashed');
    $return = spilMailReturn();
    Storage::disk('dashed')->put(ltrim($return->creditOrder->invoicePath(), '/'), '%PDF-1.4');

    $mail = new OrderReturnProcessedMail($return);
    $mail->build();

    expect($mail->diskAttachments)->toHaveCount(1);
});

it('bouwt beide mails zonder fout en zet ze in de wachtrij', function () {
    Mail::fake();
    $return = spilMailReturn();

    Mail::to('klant@example.com')->queue(new OrderReturnProcessedMail($return));
    Mail::to('klant@example.com')->queue(new OrderReturnRefundedMail($return));

    Mail::assertQueued(OrderReturnProcessedMail::class);
    Mail::assertQueued(OrderReturnRefundedMail::class);
});
