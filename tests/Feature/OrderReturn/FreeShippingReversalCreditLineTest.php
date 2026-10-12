<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/InvoiceHarness.php';

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    bootInvoiceHarness();
});

function creditWithReversal(?float $amount): Order
{
    $f = spilOrder();
    $f['broek']->refundQuantity = 1;
    $f['shirt']->refundQuantity = 0;

    return $f['order']->markAsCancelledWithCredit(
        sendCustomerEmail: false,
        productsMustBeReturned: false,
        restock: false,
        refundDiscountCosts: false,
        extraOrderLineName: '',
        extraOrderLinePrice: 0,
        chosenOrderProducts: [$f['broek'], $f['shirt']],
        fulfillmentStatus: $f['order']->fulfillment_status,
        paymentMethodId: null,
        sendAdminEmail: false,
        refillGiftcard: false,
        freeShippingReversalAmount: $amount,
    );
}

it('zet een positieve verzendregel op de creditorder en verlaagt het creditbedrag', function () {
    $credit = creditWithReversal(7.95);

    $line = $credit->orderProducts()->where('sku', FreeShippingReversal::SKU)->first();

    expect($line)->not->toBeNull()
        ->and((float) $line->price)->toBe(7.95)
        ->and($line->quantity)->toBe(1)
        ->and($line->product_id)->toBeNull()
        ->and(round((float) $credit->total, 2))->toBe(-32.05)
        // btw: -40/121*21 + 7.95/121*21 = -6.94 + 1.38
        ->and(round((float) $credit->btw, 2))->toBe(-5.56);
});

it('laat de creditorder ongemoeid zonder bedrag', function () {
    foreach ([null, 0.0] as $amount) {
        $credit = creditWithReversal($amount);

        expect($credit->orderProducts()->where('sku', FreeShippingReversal::SKU)->exists())->toBeFalse()
            ->and(round((float) $credit->total, 2))->toBe(-40.0);
    }
});
