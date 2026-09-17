<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Services\OrderReturn\ReturnableLines;
use Dashed\DashedEcommerceCore\Services\OrderReturn\CancellationReturnSettler;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

/** Open retour op spilOrder(): Shirt 2 van 3, Broek 1 van 1. */
function settlerReturn(array $fixture, string $status = OrderReturn::STATUS_REQUESTED): array
{
    $return = OrderReturn::create(['order_id' => $fixture['order']->id, 'email' => 'klant@example.com', 'status' => $status]);
    $shirtLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $fixture['shirt']->id, 'quantity' => 2]);
    $broekLine = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $fixture['broek']->id, 'quantity' => 1]);

    return ['return' => $return->fresh(), 'shirtLine' => $shirtLine, 'broekLine' => $broekLine];
}

/** Creditorder zoals de annuleerknop hem maakt, zonder de retour aan te raken. */
function settlerCredit(Order $order, array $quantities): Order
{
    $chosen = [];
    foreach ($order->orderProducts as $orderProduct) {
        $orderProduct->refundQuantity = $quantities[$orderProduct->id] ?? 0;
        $chosen[] = $orderProduct;
    }

    return $order->markAsCancelledWithCredit(false, false, false, false, '', 0, $chosen, 'handled', null, sendAdminEmail: false, refillGiftcard: false);
}

it('zet een open retour op verwerkt met de creditorder van de annulering', function () {
    $f = spilOrder();
    $r = settlerReturn($f, OrderReturn::STATUS_APPROVED);
    $credit = settlerCredit($f['order'], [$f['shirt']->id => 3, $f['broek']->id => 1]);

    $settled = app(CancellationReturnSettler::class)->settle($f['order'], $credit, [$f['shirt']->id => 3, $f['broek']->id => 1]);

    $return = $r['return']->fresh();
    expect($settled)->toBe(1)
        ->and($return->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($return->credit_order_id)->toBe($credit->id)
        ->and($return->processed_at)->not->toBeNull()
        ->and($return->handled_at)->not->toBeNull()
        ->and($r['shirtLine']->fresh()->processed_quantity)->toBe(2)
        ->and($r['broekLine']->fresh()->processed_quantity)->toBe(1)
        ->and($f['shirt']->fresh()->returned_quantity)->toBe(2)
        ->and($f['broek']->fresh()->returned_quantity)->toBe(1)
        ->and(ReturnableLines::remaining($f['shirt']->fresh()))->toBe(1)
        ->and($f['order']->fresh()->retour_status)->toBe('handled')
        ->and(OrderLog::where('order_id', $f['order']->id)->where('tag', 'order.return-handled-by-cancellation')->first()?->tag())->toContain('geannuleerd', $credit->invoice_id);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('verwerkt per regel niet meer dan er geannuleerd is', function () {
    $f = spilOrder();
    $r = settlerReturn($f);
    $credit = settlerCredit($f['order'], [$f['shirt']->id => 1]);

    app(CancellationReturnSettler::class)->settle($f['order'], $credit, [$f['shirt']->id => 1]);

    expect($r['return']->fresh()->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($r['shirtLine']->fresh()->processed_quantity)->toBe(1)
        ->and($r['broekLine']->fresh()->processed_quantity)->toBe(0)
        ->and($f['shirt']->fresh()->returned_quantity)->toBe(1)
        ->and($f['broek']->fresh()->returned_quantity)->toBe(0);
});

it('laat afgeronde retouren en retouren van andere bestellingen met rust', function () {
    $f = spilOrder();
    $done = [];
    foreach ([OrderReturn::STATUS_REJECTED, OrderReturn::STATUS_HANDLED, OrderReturn::STATUS_CLOSED] as $status) {
        $done[$status] = settlerReturn($f, $status)['return'];
    }
    $other = spilOrder();
    $otherReturn = settlerReturn($other)['return'];
    $credit = settlerCredit($f['order'], [$f['shirt']->id => 3, $f['broek']->id => 1]);

    $settled = app(CancellationReturnSettler::class)->settle($f['order'], $credit, [$f['shirt']->id => 3, $f['broek']->id => 1]);

    expect($settled)->toBe(0);
    foreach ($done as $status => $return) {
        expect($return->fresh()->status)->toBe($status)
            ->and($return->fresh()->credit_order_id)->toBeNull();
    }
    expect($otherReturn->fresh()->status)->toBe(OrderReturn::STATUS_REQUESTED)
        ->and(OrderLog::where('order_id', $f['order']->id)->where('tag', 'order.return-handled-by-cancellation')->exists())->toBeFalse();
});

it('handelt een open retour af zodra de annuleerknop een creditorder maakt', function () {
    $f = spilOrder();
    $r = settlerReturn($f, OrderReturn::STATUS_APPROVED);

    \Livewire\Livewire::test(\Dashed\DashedEcommerceCore\Livewire\Orders\CancelOrder::class, ['order' => $f['order']])
        ->callAction('action', data: [
            "order_product_{$f['shirt']->id}" => 3,
            "order_product_{$f['broek']->id}" => 0,
            'fulfillment_status' => 'handled',
            'payment_method_id' => null,
            'send_customer_email' => false,
            'products_must_be_returned' => false,
            'restock' => false,
            'refund_discount_costs' => false,
            'extra_order_line' => false,
        ])
        ->assertHasNoActionErrors();

    $credit = Order::where('credit_for_order_id', $f['order']->id)->firstOrFail();
    $return = $r['return']->fresh();
    expect($return->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($return->credit_order_id)->toBe($credit->id)
        ->and($r['shirtLine']->fresh()->processed_quantity)->toBe(2)
        ->and($r['broekLine']->fresh()->processed_quantity)->toBe(0);
});
