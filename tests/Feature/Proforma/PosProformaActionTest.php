<?php

// tests/Feature/Proforma/PosProformaActionTest.php

use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;
use Dashed\DashedEcommerceCore\Classes\ProformaOrderService;

it('creates and mails a proforma from a pos cart with shipping allowed', function () {
    Mail::fake();
    Customsetting::set('pos_allow_proforma', true);
    $cashier = User::factory()->create();
    $posCart = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'pos-2',
        'email' => 'klant@example.com',
        'products' => [['id' => null, 'name' => 'Dienst', 'price' => 50.0, 'vat_rate' => 21, 'quantity' => 1, 'extra' => [], 'customProduct' => true, 'identifier' => 'x']],
    ]);

    $order = ProformaOrderService::createAndSend($posCart, $cashier, allowShipping: true);

    expect($order->is_proforma)->toBeTrue()->and($order->proforma_allow_shipping)->toBeTrue();
    Mail::assertSent(ProformaCheckoutMail::class);
});
