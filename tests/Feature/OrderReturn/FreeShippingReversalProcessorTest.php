<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/FreeShippingReversalFixtures.php';
require_once __DIR__ . '/../../Support/InvoiceHarness.php';

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;
use Dashed\DashedEcommerceCore\Mail\OrderReturn\OrderReturnProcessedMail;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    bootInvoiceHarness();
});

/** Goedgekeurde retour: 1 shirt en de broek, rest 40 (onder de drempel van 60). */
if (! function_exists('reversalReturn')) {
    function reversalReturn(): array
    {
        $f = reversalFixture();
        $return = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
        $shirtLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 1]);
        $broekLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['broek']->id, 'quantity' => 1]);

        return $f + ['return' => $return->fresh(), 'lines' => [
            ['order_return_line_id' => $shirtLine->id, 'quantity' => 1],
            ['order_return_line_id' => $broekLine->id, 'quantity' => 1],
        ]];
    }
}

if (! function_exists('reversalLine')) {
    function reversalLine($credit)
    {
        return $credit->orderProducts()->where('sku', FreeShippingReversal::SKU)->first();
    }
}

it('houdt standaard de verzendkosten in', function () {
    $f = reversalReturn();

    $credit = app(ReturnProcessor::class)->process($f['return'], $f['lines'], ['restock' => false]);

    expect((float) reversalLine($credit)->price)->toBe(7.95)
        ->and(round((float) $credit->total, 2))->toBe(-52.05);
});

it('houdt niets in als de beheerder het uitzet', function () {
    $f = reversalReturn();

    $credit = app(ReturnProcessor::class)->process($f['return'], $f['lines'], ['restock' => false, 'charge_shipping' => false]);

    expect(reversalLine($credit))->toBeNull()
        ->and(round((float) $credit->total, 2))->toBe(-60.0);
});

it('accepteert een lager bedrag maar nooit een hoger', function () {
    $f = reversalReturn();
    $credit = app(ReturnProcessor::class)->process($f['return'], $f['lines'], ['restock' => false, 'charge_shipping_amount' => 4]);
    expect((float) reversalLine($credit)->price)->toBe(4.0);

    $g = reversalReturn();
    $credit = app(ReturnProcessor::class)->process($g['return'], $g['lines'], ['restock' => false, 'charge_shipping_amount' => 25]);
    expect((float) reversalLine($credit)->price)->toBe(7.95);
});

it('noemt de inhouding in de gecrediteerde regels van de mail', function () {
    $f = reversalReturn();
    app(ReturnProcessor::class)->process($f['return'], $f['lines'], ['restock' => false]);

    $mail = new OrderReturnProcessedMail($f['return']->fresh(['lines', 'creditOrder']));
    $ref = new ReflectionMethod($mail, 'replaceReturnVariables');
    $ref->setAccessible(true);

    expect($ref->invoke($mail, ':creditedLines:', true))->toContain('Verzendkosten (bestelling onder gratis verzending): -')
        ->and($ref->invoke($mail, ':creditedLines:', false))->toContain('1x Shirt');
});

it('houdt bij een tweede retour van dezelfde bestelling niet nogmaals in', function () {
    $f = reversalReturn();
    $first = app(ReturnProcessor::class)->process($f['return'], $f['lines'], ['restock' => false]);
    expect(reversalLine($first))->not->toBeNull();

    $second = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $line = OrderReturnLine::create(['order_return_id' => $second->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 1]);

    $credit = app(ReturnProcessor::class)->process($second->fresh(), [['order_return_line_id' => $line->id, 'quantity' => 1]], ['restock' => false, 'confirm_existing_credit' => true]);

    expect(reversalLine($credit))->toBeNull();
});
