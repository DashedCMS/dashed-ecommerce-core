<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/InvoiceHarness.php';

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    bootInvoiceHarness();
});

if (! function_exists('creditWithReversal')) {
    function creditWithReversal(?float $amount, ?int $vatRate = null, $paymentMethodId = null): Order
    {
        $f = spilOrder();
        if ($vatRate !== null) {
            // Regels van de oorspronkelijke order met een ander tarief (OSS of verlegde btw).
            $f['order']->orderProducts()->update(['vat_rate' => $vatRate]);
        }
        $f['broek']->refundQuantity = 1;
        $f['shirt']->refundQuantity = 0;

        return $f['order']->fresh()->markAsCancelledWithCredit(
            sendCustomerEmail: false,
            productsMustBeReturned: false,
            restock: false,
            refundDiscountCosts: false,
            extraOrderLineName: '',
            extraOrderLinePrice: 0,
            chosenOrderProducts: [$f['broek']->fresh()->setAttribute('refundQuantity', 1), $f['shirt']->fresh()->setAttribute('refundQuantity', 0)],
            fulfillmentStatus: $f['order']->fulfillment_status,
            paymentMethodId: $paymentMethodId,
            sendAdminEmail: false,
            refillGiftcard: false,
            freeShippingReversalAmount: $amount,
        );
    }
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

it('volgt het btw-tarief van de gecrediteerde regels (19%)', function () {
    $credit = creditWithReversal(7.95, 19);
    $line = $credit->orderProducts()->where('sku', FreeShippingReversal::SKU)->first();

    expect((float) $line->vat_rate)->toBe(19.0)
        ->and(round((float) $line->btw, 2))->toBe(round(7.95 / 119 * 19, 2))
        // -40/119*19 + 7.95/119*19
        ->and(round((float) $credit->btw, 2))->toBe(round(-32.05 / 119 * 19, 2))
        ->and(array_keys($credit->vat_percentages))->toBe([19]);
});

it('rekent geen btw over de verzendregel bij verlegde btw (0%)', function () {
    $credit = creditWithReversal(7.95, 0);
    $line = $credit->orderProducts()->where('sku', FreeShippingReversal::SKU)->first();

    expect((float) $line->vat_rate)->toBe(0.0)
        ->and(round((float) $line->btw, 2))->toBe(0.0)
        ->and(round((float) $credit->btw, 2))->toBe(0.0)
        ->and(array_keys($credit->vat_percentages))->not->toContain('21');
});

it('maakt een terugbetaling van het creditbedrag na inhouding', function () {
    $method = PaymentMethod::create(['site_id' => \Dashed\DashedCore\Classes\Sites::getActive(), 'name' => 'iDEAL', 'psp' => 'own', 'type' => 'online']);
    $credit = creditWithReversal(7.95, null, $method->id);

    $refund = $credit->orderPayments()->first();

    expect($refund)->not->toBeNull()
        ->and(round((float) $refund->amount, 2))->toBe(-32.05)
        ->and($refund->status)->toBe('paid');
});
