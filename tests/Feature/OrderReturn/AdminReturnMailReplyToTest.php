<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Mail\AdminNewOrderReturnMail;
use Dashed\DashedEcommerceCore\Mail\AdminNewOrderReturnReplyMail;

it('replies to the order email on the new return mail', function () {
    $order = Order::create(['site_id' => 'site', 'email' => 'klant@example.com', 'first_name' => 'Jan', 'last_name' => 'Jansen', 'invoice_id' => 'INV-R1', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'site_id' => 'site', 'email' => 'klant@example.com']);

    $mail = new AdminNewOrderReturnMail($return->fresh());
    $mail->build();

    expect($mail->hasReplyTo('klant@example.com', 'Jan Jansen'))->toBeTrue();
});

it('replies to the order email on the return reply mail', function () {
    $order = Order::create(['site_id' => 'site', 'email' => 'klant@example.com', 'first_name' => 'Jan', 'last_name' => 'Jansen', 'invoice_id' => 'INV-R2', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'site_id' => 'site', 'email' => 'klant@example.com']);

    $mail = new AdminNewOrderReturnReplyMail($return->fresh(), 'Wanneer krijg ik mijn geld terug?');
    $mail->build();

    expect($mail->hasReplyTo('klant@example.com'))->toBeTrue();
});

it('falls back to the return email when the order has no email', function () {
    $order = Order::create(['site_id' => 'site', 'email' => '', 'invoice_id' => 'INV-R3', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'site_id' => 'site', 'email' => 'retour@example.com']);

    $mail = new AdminNewOrderReturnMail($return->fresh());
    $mail->build();

    expect($mail->hasReplyTo('retour@example.com'))->toBeTrue();
});
