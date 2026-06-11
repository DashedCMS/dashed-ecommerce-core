<?php

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRequestedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRejectedMail;

it('constructs all three return mailables without throwing', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-TEST-1']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com']);

    expect(new OrderReturnRequestedMail($return))->toBeInstanceOf(OrderReturnRequestedMail::class)
        ->and(new OrderReturnApprovedMail($return))->toBeInstanceOf(OrderReturnApprovedMail::class)
        ->and(new OrderReturnRejectedMail($return))->toBeInstanceOf(OrderReturnRejectedMail::class);
});

it('queues the requested mail via Mail::fake without throwing', function () {
    Mail::fake();

    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-TEST-2']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com']);

    Mail::to($order->email)->queue(new OrderReturnRequestedMail($return));

    Mail::assertQueued(OrderReturnRequestedMail::class, fn ($mail) => $mail->orderReturn->is($return));
});

it('queues the approved mail via Mail::fake without throwing', function () {
    Mail::fake();

    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-TEST-3']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'admin_note' => 'Stuur maar terug.']);

    Mail::to($order->email)->queue(new OrderReturnApprovedMail($return));

    Mail::assertQueued(OrderReturnApprovedMail::class);
});

it('queues the rejected mail via Mail::fake without throwing', function () {
    Mail::fake();

    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-TEST-4']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com', 'rejected_reason' => 'Buiten de retourtermijn.']);

    Mail::to($order->email)->queue(new OrderReturnRejectedMail($return));

    Mail::assertQueued(OrderReturnRejectedMail::class);
});

it('exposes the correct emailTemplateName for each mail', function () {
    expect(OrderReturnRequestedMail::emailTemplateName())->toBe('Retour: aanvraag ontvangen')
        ->and(OrderReturnApprovedMail::emailTemplateName())->toBe('Retour: goedgekeurd')
        ->and(OrderReturnRejectedMail::emailTemplateName())->toBe('Retour: afgekeurd');
});

it('includes return-specific variables in availableVariables', function () {
    $vars = OrderReturnRequestedMail::availableVariables();

    expect($vars)->toContain('returnRequestedAt')
        ->and($vars)->toContain('returnReason')
        ->and($vars)->toContain('rejectedReason')
        ->and($vars)->toContain('adminNote');
});

it('defaultBlocks for requested mail includes invoiceId and returnRequestedAt', function () {
    $blocks = OrderReturnRequestedMail::defaultBlocks();
    $allText = collect($blocks)->map(fn ($b) => json_encode($b))->implode(' ');

    expect($allText)->toContain(':invoiceId:')
        ->and($allText)->toContain(':returnRequestedAt:');
});

it('defaultBlocks for rejected mail includes rejectedReason', function () {
    $blocks = OrderReturnRejectedMail::defaultBlocks();
    $allText = collect($blocks)->map(fn ($b) => json_encode($b))->implode(' ');

    expect($allText)->toContain(':rejectedReason:');
});
