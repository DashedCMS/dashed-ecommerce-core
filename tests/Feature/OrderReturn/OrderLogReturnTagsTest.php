<?php

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

it('rendert elke retour- en terugbetaaltag zonder ERROR', function () {
    $user = User::factory()->create(['first_name' => 'Robin', 'last_name' => 'Test']);
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'first_name' => 'Kim', 'last_name' => 'Jansen']);

    $tags = [
        'order.return-requested', 'order.return-approved', 'order.return-rejected', 'order.return-handled',
        'order.return-label-failed', 'order.return-customer-replied',
        'order.return-registered-by-admin', 'order.return-processed', 'order.return-closed', 'order.return-refunded',
        'order.return-mail-skipped-bol', 'order.return-processed.mail.failed', 'order.return-refunded.mail.failed',
        'order.return.full', 'order.return.partial', 'order.return.refund-requested', 'order.refund.registered',
        'order.changed-retour-status-to-handled',
    ];

    foreach ($tags as $tag) {
        $log = new OrderLog();
        $log->order_id = $order->id;
        $log->user_id = $user->id;
        $log->tag = $tag;
        $log->save();

        expect($log->fresh()->tag())->not->toContain('ERROR', "tag {$tag}");
    }
});

it('valt voor een onbekende tag terug op de notitie', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'first_name' => 'Kim', 'last_name' => 'Jansen']);
    $log = new OrderLog();
    $log->order_id = $order->id;
    $log->tag = 'iets.onbekends';
    $log->note = 'Vrije notitie';
    $log->save();

    expect($log->fresh()->tag())->toBe('Vrije notitie');
});
