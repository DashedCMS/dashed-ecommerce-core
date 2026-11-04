<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/InvoiceHarness.php';

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnProcessor;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ShippingCostsRefund;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    bootInvoiceHarness();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

/** spilOrder() plus een verzendregel van 6,95 en een goedgekeurde retour op alles. */
function shippingRefundReturn(int $shirts = 3): array
{
    $f = spilOrder(['subtotal' => 106.95, 'total' => 106.95]);
    $shipping = OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $f['order']->id, 'name' => 'Verzenden', 'quantity' => 1, 'price' => 6.95, 'vat_rate' => 21, 'sku' => 'shipping_costs',
    ]));
    $return = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now()]);
    $shirtLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['shirt']->id, 'quantity' => $shirts]);
    $broekLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['broek']->id, 'quantity' => 1]);

    return $f + ['shipping' => $shipping, 'return' => $return->fresh(), 'shirtLine' => $shirtLine, 'broekLine' => $broekLine];
}

it('crediteert bij een volledige retour ook de verzendkosten', function () {
    $f = shippingRefundReturn();

    $credit = app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 3],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 1],
    ], ['restock' => false]);

    $shippingLine = $credit->orderProducts()->where('sku', 'shipping_costs')->first();

    expect(round((float) $credit->total, 2))->toBe(-106.95)
        ->and($shippingLine)->not->toBeNull()
        ->and($shippingLine->quantity)->toBe(-1)
        ->and(round((float) $shippingLine->price, 2))->toBe(-6.95)
        ->and($f['return']->fresh()->refundableAmount())->toBe(106.95);
});

it('laat de verzendkosten staan bij een deelretour', function () {
    $f = shippingRefundReturn(2);

    $credit = app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 2],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 1],
    ], ['restock' => false]);

    expect($credit->orderProducts()->where('sku', 'shipping_costs')->exists())->toBeFalse()
        ->and(round((float) $credit->total, 2))->toBe(-80.0);
});

it('slaat de verzendkosten over als refund_shipping uit staat', function () {
    $f = shippingRefundReturn();

    $credit = app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 3],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 1],
    ], ['restock' => false, 'refund_shipping' => false]);

    expect($credit->orderProducts()->where('sku', 'shipping_costs')->exists())->toBeFalse()
        ->and(round((float) $credit->total, 2))->toBe(-100.0);
});

it('telt een eerdere creditorder mee en crediteert de verzendkosten maar één keer', function () {
    $f = shippingRefundReturn(1);

    // Eerst twee shirts via de annuleerknop, met de verzendkosten erbij.
    $shirt = $f['shirt']->fresh();
    $shirt->refundQuantity = 2;
    $shipping = $f['shipping']->fresh();
    $shipping->refundQuantity = 1;
    $f['order']->fresh()->markAsCancelledWithCredit(false, false, false, false, '', 0, [$shirt, $shipping], 'handled', null, false);

    $service = app(ShippingCostsRefund::class);
    expect($service->linesToCredit($f['order']->fresh()))->toBeEmpty()
        ->and($service->isFullReturn($f['order']->fresh(), [$f['shirt']->id => 1, $f['broek']->id => 1]))->toBeTrue();

    $credit = app(ReturnProcessor::class)->process($f['return'], [
        ['order_return_line_id' => $f['shirtLine']->id, 'quantity' => 1],
        ['order_return_line_id' => $f['broekLine']->id, 'quantity' => 1],
    ], ['restock' => false, 'confirm_existing_credit' => true]);

    expect($credit->orderProducts()->where('sku', 'shipping_costs')->exists())->toBeFalse()
        ->and(round((float) $credit->total, 2))->toBe(-60.0);
});

it('rekent het verzendbedrag uit dat nog terug moet', function () {
    $f = shippingRefundReturn();
    $service = app(ShippingCostsRefund::class);

    expect($service->amount($f['order']))->toBe(6.95)
        ->and($service->isFullReturn($f['order'], [$f['shirt']->id => 3, $f['broek']->id => 1]))->toBeTrue()
        ->and($service->isFullReturn($f['order'], [$f['shirt']->id => 2, $f['broek']->id => 1]))->toBeFalse();
});
