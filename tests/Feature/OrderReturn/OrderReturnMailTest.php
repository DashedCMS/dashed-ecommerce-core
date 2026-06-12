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

it('defaultBlocks for requested mail includes orderNumber and returnRequestedAt', function () {
    $blocks = OrderReturnRequestedMail::defaultBlocks();
    $allText = collect($blocks)->map(fn ($b) => json_encode($b))->implode(' ');

    expect($allText)->toContain(':orderNumber:')
        ->and($allText)->toContain(':returnRequestedAt:');
});

it('defaultBlocks for rejected mail includes rejectedReason', function () {
    $blocks = OrderReturnRejectedMail::defaultBlocks();
    $allText = collect($blocks)->map(fn ($b) => json_encode($b))->implode(' ');

    expect($allText)->toContain(':rejectedReason:');
});

it('return mail defaultBlocks use the order-details block like other order mails', function () {
    foreach ([
        \Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRequestedMail::defaultBlocks(),
        \Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnApprovedMail::defaultBlocks(),
        \Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRejectedMail::defaultBlocks(),
    ] as $blocks) {
        $types = collect($blocks)->pluck('type')->all();
        expect($types)->toContain('order-details');
    }
});

it('escapes free-text return variables in HTML context but not in plain-text subject context', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-XSS-9']);
    $return = OrderReturn::create([
        'order_id' => $order->id,
        'email' => 'a@b.nl',
        'rejected_reason' => '<script>alert(1)</script>',
    ]);

    $mail = new OrderReturnRejectedMail($return);

    $ref = new ReflectionMethod($mail, 'replaceReturnVariables');
    $ref->setAccessible(true);

    // HTML body: angle brackets must be entity-encoded
    $html = $ref->invoke($mail, 'Reden: :rejectedReason:', true);
    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');

    // Subject / plain-text: value passes through unmodified
    $subject = $ref->invoke($mail, 'Reden: :rejectedReason:', false);
    expect($subject)->toContain('<script>');
});

it('substitutes orderNumber with the invoice id when present', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-42']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $mail = new OrderReturnApprovedMail($return);
    $ref = new ReflectionMethod($mail, 'replaceReturnVariables');
    $ref->setAccessible(true);

    expect($ref->invoke($mail, 'Bestelling :orderNumber:', false))->toBe('Bestelling INV-42');
});

it('falls back to #order_id for orderNumber when invoice id is empty', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);

    $mail = new OrderReturnApprovedMail($return);
    $ref = new ReflectionMethod($mail, 'replaceReturnVariables');
    $ref->setAccessible(true);

    expect($ref->invoke($mail, 'Bestelling :orderNumber:', false))->toBe('Bestelling #' . $order->id);
});

it('approved subject default no longer contains empty parentheses', function () {
    expect(OrderReturnApprovedMail::defaultSubject())
        ->not->toContain('()')
        ->toContain(':orderNumber:');
});

use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Models\ReturnReason;

it('includes the reason note in the line summary and escapes it in HTML', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-9']);
    $product = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 2, 'price' => 10]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    OrderReturnLine::create([
        'order_return_id' => $return->id,
        'order_product_id' => $product->id,
        'quantity' => 1,
        'reason_note' => '<script>x</script>',
    ]);

    $mail = new \Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnRequestedMail($return->fresh('lines'));
    $ref = new ReflectionMethod($mail, 'replaceReturnVariables');
    $ref->setAccessible(true);
    $html = $ref->invoke($mail, ':returnLines:', true);

    expect($html)->toContain('1x Shirt')
        ->and($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('renders the return lines summary and escapes product names in HTML', function () {
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-7']);
    $product = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt <b>', 'quantity' => 3, 'price' => 20]);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl']);
    OrderReturnLine::create([
        'order_return_id' => $return->id,
        'order_product_id' => $product->id,
        'quantity' => 2,
    ]);

    $mail = new OrderReturnRequestedMail($return->fresh('lines'));

    $ref = new ReflectionMethod($mail, 'replaceReturnVariables');
    $ref->setAccessible(true);

    $html = $ref->invoke($mail, 'Regels: :returnLines:', true);
    expect($html)->toContain('2x Shirt')
        ->and($html)->not->toContain('<b>')
        ->and($html)->toContain('&lt;b&gt;');

    $plain = $ref->invoke($mail, 'Regels: :returnLines:', false);
    expect($plain)->toContain('2x Shirt <b>');
});
