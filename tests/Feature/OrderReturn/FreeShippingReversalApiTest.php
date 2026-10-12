<?php

require_once __DIR__ . '/../../Support/SpilOrder.php';
require_once __DIR__ . '/../../Support/FreeShippingReversalFixtures.php';
require_once __DIR__ . '/../../Support/InvoiceHarness.php';

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OrderReturnController;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    bootInvoiceHarness();
    // De kassa probeert na het annuleren een bon te printen; dat hoort niet in de test.
    \Dashed\DashedCore\Models\Customsetting::set('pos_auto_print_receipt', false);
});

/** Roept PointOfSaleApiController::cancelOrder aan met de aantallen per orderregel. */
function posCancel(array $f, int $shirt, int $broek, array $extra = [], array $cancelExtra = []): ?Order
{
    $f['order']->forceFill(['order_origin' => 'pos'])->save();

    $orderProducts = $f['order']->fresh()->orderProducts->map(function ($op) use ($f, $shirt, $broek) {
        return $op->toArray() + ['refundQuantity' => $op->id === $f['shirt']->id ? $shirt : $broek];
    })->all();

    $request = Request::create('/pos/cancel', 'POST', $extra + ['order' => [
        'id' => $f['order']->id,
        'cancelData' => $cancelExtra + [
            'sendCustomerEmail' => false,
            'productsMustBeReturned' => false,
            'restock' => false,
            'refundDiscountCosts' => false,
            'orderProducts' => $orderProducts,
            'extraOrderLine' => false,
            'fulfillmentStatus' => 'unhandled',
            'paymentMethodId' => null,
        ],
    ]]);

    app(PointOfSaleApiController::class)->cancelOrder($request);

    return Order::where('credit_for_order_id', $f['order']->id)->first();
}

function creditChargeLine(?Order $credit)
{
    return $credit?->orderProducts()->where('sku', FreeShippingReversal::SKU)->first();
}

it('POS: houdt standaard verzendkosten in bij een deelannulering', function () {
    $credit = posCancel(reversalFixture(), 1, 1);

    expect($credit)->not->toBeNull()
        ->and((float) creditChargeLine($credit)->price)->toBe(7.95);
});

it('POS: houdt niets in bij chargeShipping false, ook als tekst of onder een andere spelling', function () {
    foreach ([[[], ['chargeShipping' => false]], [[], ['chargeShipping' => 'false']], [[], ['charge_shipping' => 'false']], [['charge_shipping' => '0'], []]] as [$extra, $cancelExtra]) {
        $credit = posCancel(reversalFixture(), 1, 1, $extra, $cancelExtra);

        expect($credit)->not->toBeNull()
            ->and(creditChargeLine($credit))->toBeNull();
    }
});

it('POS: houdt niets in bij een volledige annulering', function () {
    $credit = posCancel(reversalFixture(), 3, 1);

    expect($credit)->not->toBeNull()
        ->and(creditChargeLine($credit))->toBeNull();
});

/** Roept OrderReturnController::handle aan voor een goedgekeurde retour van shirt en broek. */
function handleReturn(array $body): ?Order
{
    $f = reversalFixture();
    $return = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => OrderReturn::STATUS_APPROVED, 'approved_at' => now(), 'site_id' => $f['order']->site_id]);
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 1]);
    OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['broek']->id, 'quantity' => 1]);

    $request = Request::create("/api/v1/returns/{$return->id}/handle", 'POST', $body + ['restock' => false]);
    app(OrderReturnController::class)->handle($request, $return->id);

    return Order::where('credit_for_order_id', $f['order']->id)->first();
}

it('mobiele API: houdt standaard verzendkosten in zonder parameter', function () {
    $credit = handleReturn([]);

    expect($credit)->not->toBeNull()
        ->and((float) creditChargeLine($credit)->price)->toBe(7.95);
});

it('mobiele API: houdt niets in met charge_shipping false', function () {
    $credit = handleReturn(['charge_shipping' => false]);

    expect($credit)->not->toBeNull()
        ->and(creditChargeLine($credit))->toBeNull();
});
