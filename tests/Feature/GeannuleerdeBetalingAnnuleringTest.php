<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function annuleringsBetaling(string $orderStatus): OrderPayment
{
    $order = Order::create(['email' => 'klant@example.com', 'status' => $orderStatus, 'total' => 1936.00]);

    return $order->orderPayments()->create(['status' => 'pending', 'amount' => 1936.00, 'psp' => 'paynl']);
}

it('annuleert een bestelling in de checkout als de enige betaling mislukt', function () {
    expect(annuleringsBetaling('pending')->changeStatus('cancelled'))->toBe('cancelled');
});

it('annuleert geen bestelling die op bevestiging wacht als een betaallink wordt afgebroken', function () {
    // Handmatige of kassabestelling met een betaallink: de klant opent de
    // link en breekt af. De bestelling wacht nog steeds op betaling (bijv.
    // per overboeking) en mag niet vanzelf vervallen.
    expect(annuleringsBetaling('waiting_for_confirmation')->changeStatus('cancelled'))->toBe('');
});

it('annuleert geen betaalde bestelling', function () {
    expect(annuleringsBetaling('paid')->changeStatus('cancelled'))->toBe('');
});
