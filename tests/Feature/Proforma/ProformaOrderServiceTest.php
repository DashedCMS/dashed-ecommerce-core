<?php

// tests/Feature/Proforma/ProformaOrderServiceTest.php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Mail\ProformaCheckoutMail;
use Dashed\DashedEcommerceCore\Classes\ProformaOrderService;

it('creates a sent proforma concept with a custom product and no invoice', function () {
    Mail::fake();
    $cashier = User::factory()->create();
    $posCart = POSCart::create([
        'user_id' => $cashier->id,
        'identifier' => 'pos-1',
        'email' => 'klant@example.com',
        'first_name' => 'Jan',
        'products' => [[
            'id' => null,
            'name' => 'Maatwerk dienst',
            'price' => 121.0,
            'vat_rate' => 21,
            'quantity' => 1,
            'extra' => [],
            'customProduct' => true,
            'identifier' => 'abc',
        ]],
    ]);

    $order = ProformaOrderService::createAndSend($posCart, $cashier, allowShipping: true);

    expect($order->status)->toBe(Order::STATUS_CONCEPT)
        ->and($order->is_proforma)->toBeTrue()
        ->and($order->proforma_allow_shipping)->toBeTrue()
        ->and($order->proforma_sent_at)->not->toBeNull()
        ->and($order->invoice_id)->toBeNull()
        ->and($order->orderProducts()->count())->toBe(1)
        ->and($order->orderProducts()->first()->product_id)->toBeNull()
        ->and($order->orderProducts()->first()->name)->toBe('Maatwerk dienst');

    Mail::assertSent(ProformaCheckoutMail::class);
});

/**
 * Na het versturen van een proforma moet de POS-cart afgesloten worden, zodat
 * de volgende verkoop met een verse, lege cart begint en klantgegevens
 * (e-mail) + verzending van deze proforma niet blijven staan.
 */
it('finishes the POS cart after sending a proforma so the next sale starts fresh', function () {
    Illuminate\Support\Facades\Mail::fake();
    $cashier = Dashed\DashedCore\Models\User::factory()->create();

    $posCart = Dashed\DashedEcommerceCore\Models\POSCart::create([
        'identifier' => 'pos-finish',
        'user_id' => $cashier->id,
        'status' => 'active',
        'email' => 'klant@example.com',
        'products' => [[
            'id' => null, 'name' => 'Dienst', 'price' => 50.0, 'vat_rate' => 21,
            'quantity' => 1, 'extra' => [], 'customProduct' => true, 'identifier' => 'x',
        ]],
    ]);

    Dashed\DashedEcommerceCore\Classes\ProformaOrderService::createAndSend($posCart, $cashier, false);

    expect($posCart->fresh()->status)->toBe('finished')
        ->and(Dashed\DashedEcommerceCore\Models\POSCart::where('user_id', $cashier->id)->where('status', 'active')->exists())->toBeFalse();
});
