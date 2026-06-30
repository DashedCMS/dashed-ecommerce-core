<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;
use Dashed\DashedEcommerceCore\Classes\ProformaOrderService;

/**
 * De POS-actie zet het opgegeven e-mailadres op de cart en roept dit aan.
 * Dit borgt dat de proforma daadwerkelijk naar dat adres gemaild wordt,
 * met een werkende /proforma/{hash}-link, zodat de klant de order kan voltooien.
 */
it('mails the proforma to the cashier-provided recipient with a working checkout link', function () {
    Mail::fake();
    $cashier = User::factory()->create();

    $posCart = POSCart::create([
        'identifier' => 'pos-send-1',
        'user_id' => $cashier->id,
        'email' => 'ontvanger@voorbeeld.nl',
        'products' => [[
            'id' => null,
            'name' => 'Maatwerk dienst',
            'price' => 250.0,
            'vat_rate' => 21,
            'quantity' => 1,
            'extra' => [],
            'customProduct' => true,
            'identifier' => 'abc',
        ]],
    ]);

    $order = ProformaOrderService::createAndSend($posCart, $cashier, allowShipping: false);

    expect($order->status)->toBe(Order::STATUS_CONCEPT)
        ->and($order->is_proforma)->toBeTrue()
        ->and($order->email)->toBe('ontvanger@voorbeeld.nl');

    Mail::assertSent(ProformaCheckoutMail::class, function (ProformaCheckoutMail $mail) use ($order) {
        return $mail->hasTo('ontvanger@voorbeeld.nl')
            && str_contains($mail->render(), '/proforma/' . $order->hash);
    });
});
