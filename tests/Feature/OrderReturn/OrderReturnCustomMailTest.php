<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnCustomMail;

it('exposes the emailTemplateName for the custom mail', function () {
    expect(OrderReturnCustomMail::emailTemplateName())->toBe('Retour: bericht aan klant');
});

it('includes message in availableVariables and defaultBlocks', function () {
    $vars = OrderReturnCustomMail::availableVariables();
    $blocks = collect(OrderReturnCustomMail::defaultBlocks())
        ->map(fn ($b) => json_encode($b))->implode(' ');

    expect($vars)->toContain('message')
        ->and($blocks)->toContain(':message:');
});

it('stores the per-send message and subject on public properties', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-CM-1']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com']);

    $mail = new OrderReturnCustomMail($return, '<p>Hoi daar</p>', 'Onderwerp X');

    expect($mail->messageBody)->toBe('<p>Hoi daar</p>')
        ->and($mail->subjectOverride)->toBe('Onderwerp X')
        ->and($mail->orderReturn->is($return))->toBeTrue();
});

it('substitutes :message: into the rendered body via replaceReturnVariables', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-CM-2']);
    $return = OrderReturn::create(['order_id' => $order->id, 'email' => 'klant@example.com']);

    $mail = new OrderReturnCustomMail($return, '<p>Uniek bericht 123</p>');

    $method = new ReflectionMethod($mail, 'replaceReturnVariables');
    $method->setAccessible(true);
    $result = $method->invoke($mail, 'Voor :message: na', false);

    expect($result)->toBe('Voor <p>Uniek bericht 123</p> na');
});

it('provides a non-empty default message for prefilling', function () {
    expect(OrderReturnCustomMail::defaultMessage())->toBeString()->not->toBe('');
});

it('includes a button to view and reply on the return page', function () {
    $blocks = collect(OrderReturnCustomMail::defaultBlocks())->map(fn ($b) => json_encode($b))->implode(' ');

    expect($blocks)->toContain('Bekijk en reageer op je retour')
        ->and($blocks)->toContain(':returnStatusUrl:');
});
