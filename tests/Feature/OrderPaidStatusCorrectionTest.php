<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function statusCorrectieOrder(string $status, float $paidAmount, float $total = 31.90): Order
{
    $order = Order::create(['email' => 'klant@example.com', 'status' => $status, 'total' => $total]);

    if ($paidAmount > 0) {
        $order->orderPayments()->create(['status' => 'paid', 'amount' => $paidAmount, 'psp' => 'paynl']);
    }

    return $order->fresh();
}

it('vraagt om correctie wanneer alles betaald is maar de status achterloopt', function () {
    expect(statusCorrectieOrder('waiting_for_confirmation', 31.90)->needsPaidStatusCorrection())->toBeTrue();
});

it('vraagt om correctie bij een gedeeltelijk-betaald-status die intussen volledig gedekt is', function () {
    expect(statusCorrectieOrder('partially_paid', 31.90)->needsPaidStatusCorrection())->toBeTrue();
});

it('vraagt niet om correctie zolang er nog een bedrag openstaat', function () {
    expect(statusCorrectieOrder('waiting_for_confirmation', 10.00)->needsPaidStatusCorrection())->toBeFalse();
});

it('vraagt niet om correctie zonder enkele betaling', function () {
    expect(statusCorrectieOrder('pending', 0)->needsPaidStatusCorrection())->toBeFalse();
});

it('vraagt niet om correctie op een bestelling die al betaald is', function () {
    expect(statusCorrectieOrder('paid', 31.90)->needsPaidStatusCorrection())->toBeFalse();
});

it('laat geannuleerde en retour-bestellingen met rust', function () {
    expect(statusCorrectieOrder('cancelled', 31.90)->needsPaidStatusCorrection())->toBeFalse()
        ->and(statusCorrectieOrder('return', 31.90)->needsPaidStatusCorrection())->toBeFalse();
});

it('laat een concept met rust', function () {
    expect(statusCorrectieOrder(Order::STATUS_CONCEPT, 31.90)->needsPaidStatusCorrection())->toBeFalse();
});

it('ziet een bestelling zonder totaal niet aan voor volledig betaald', function () {
    expect(statusCorrectieOrder('pending', 0, total: 0)->needsPaidStatusCorrection())->toBeFalse();
});
