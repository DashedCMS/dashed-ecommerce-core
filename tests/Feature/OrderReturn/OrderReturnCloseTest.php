<?php

use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;

function closeTestReturn(string $status = OrderReturn::STATUS_APPROVED): OrderReturn
{
    $order = Order::create(['email' => 'a@b.nl', 'status' => 'paid', 'invoice_id' => 'INV-CLOSE', 'retour_status' => 'waiting_for_return']);

    return OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => $status]);
}

it('sluit een goedgekeurde retour met reden, zonder creditorder en zonder mail', function () {
    Mail::fake();
    $return = closeTestReturn();

    $return->close('Klant heeft niets opgestuurd');

    $fresh = $return->fresh();
    expect($fresh->status)->toBe(OrderReturn::STATUS_CLOSED)
        ->and($fresh->closed_reason)->toBe('Klant heeft niets opgestuurd')
        ->and($fresh->closed_at)->not->toBeNull()
        ->and($fresh->credit_order_id)->toBeNull()
        ->and($fresh->order->retour_status)->toBe('handled')
        ->and(OrderLog::where('order_id', $fresh->order_id)->where('tag', 'order.return-closed')->exists())->toBeTrue()
        ->and(Order::where('credit_for_order_id', $fresh->order_id)->count())->toBe(0);

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('telt een gesloten retour niet meer mee in de badge', function () {
    $return = closeTestReturn();
    $return->close('Afgewezen na controle');

    expect(OrderReturnResource::getNavigationBadge())->toBeNull();
});

it('weigert sluiten zonder reden', function () {
    $return = closeTestReturn();

    expect(fn () => $return->close('   '))->toThrow(InvalidArgumentException::class);
    expect($return->fresh()->status)->toBe(OrderReturn::STATUS_APPROVED);
});

it('weigert sluiten in een andere stand dan goedgekeurd', function () {
    foreach ([OrderReturn::STATUS_REQUESTED, OrderReturn::STATUS_REJECTED, OrderReturn::STATUS_HANDLED, OrderReturn::STATUS_CLOSED] as $status) {
        $return = closeTestReturn($status);
        expect(fn () => $return->close('Reden'))->toThrow(InvalidArgumentException::class);
        expect($return->fresh()->status)->toBe($status);
    }
});
