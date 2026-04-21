<?php

use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

it('cancels only pending rows for matching email', function () {
    $pending = AbandonedCartEmail::create([
        'email' => 'klant@example.test',
        'trigger_type' => 'cancelled_order',
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->addHour(),
    ]);

    $alreadySent = AbandonedCartEmail::create([
        'email' => 'klant@example.test',
        'trigger_type' => 'cancelled_order',
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->subHour(),
        'sent_at' => now()->subMinutes(10),
    ]);

    $otherEmail = AbandonedCartEmail::create([
        'email' => 'ander@example.test',
        'trigger_type' => 'cart_with_email',
        'email_number' => 1,
        'flow_step_id' => null,
        'send_at' => now()->addHour(),
    ]);

    $count = AbandonedCartEmail::cancelPendingForEmail('klant@example.test', 'converted');

    expect($count)->toBe(1)
        ->and($pending->fresh()->cancelled_at)->not->toBeNull()
        ->and($pending->fresh()->cancelled_reason)->toBe('converted')
        ->and($alreadySent->fresh()->cancelled_at)->toBeNull()
        ->and($otherEmail->fresh()->cancelled_at)->toBeNull();
});

it('source accessor returns null for cart trigger without cart', function () {
    $email = new AbandonedCartEmail(['trigger_type' => 'cart_with_email']);
    $email->cart_id = null;
    $email->cancelled_order_id = null;

    expect($email->source())->toBeNull();
});
