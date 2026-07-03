<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PinTerminal;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\PinTerminal as PinTerminalClass;

/**
 * Als je in de POS een pin-betaling start terwijl er nog een andere pin-betaling
 * van dezelfde order actief (pending) is, moet die vorige op 'cancelled' gezet
 * worden zodat zijn CheckPinTerminalPaymentStatusJob stopt met pollen.
 */
it('cancels a previous still-pending pin payment so its status checks stop', function () {
    $terminal = PinTerminal::create([
        'site_id' => Sites::getActive(),
        'psp' => 'nopsp',
        'pin_terminal_id' => 'TERM-1',
        'name' => ['nl' => 'Kassa terminal'],
        'active' => 1,
    ]);

    $method = PaymentMethod::create([
        'site_id' => Sites::getActive(),
        'name' => ['nl' => 'Pin'],
        'type' => 'pos',
        'active' => 1,
        'psp' => 'nopsp',
        'pin_terminal_id' => $terminal->id,
    ]);

    $order = Order::create(['email' => 'k@x.nl', 'status' => 'pending', 'total' => 100]);

    $previous = OrderPayment::create([
        'order_id' => $order->id,
        'amount' => 100,
        'status' => 'pending',
        'payment_method_id' => $method->id,
        'psp' => 'nopsp',
        'hash' => (string) Str::uuid(),
    ]);

    // Start een nieuwe pin-betaling. De echte PSP-transactie faalt (geen verbonden
    // provider), maar de vorige pending pin-betaling moet daarvoor al geannuleerd zijn.
    PinTerminalClass::startPayment($order, $method);

    expect($previous->fresh()->status)->toBe('cancelled');
});
