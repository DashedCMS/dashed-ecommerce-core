<?php

use Livewire\Livewire;
use Filament\Facades\Filament;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Models\OrderReturnLine;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages\ViewOrderReturn;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages\ListOrderReturns;

beforeEach(function () {
    Mail::fake();
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Filament::setCurrentPanel(Filament::getPanel('dashed'));
});

function filamentReturn(string $status = OrderReturn::STATUS_APPROVED): array
{
    $f = spilOrder();
    $return = OrderReturn::create(['order_id' => $f['order']->id, 'email' => 'klant@example.com', 'status' => $status, 'approved_at' => now()]);
    $line = OrderReturnLine::create(['order_return_id' => $return->id, 'order_product_id' => $f['shirt']->id, 'quantity' => 2]);

    return $f + ['return' => $return->fresh(), 'line' => $line];
}

it('toont verwerken, sluiten en afkeuren bij goedgekeurd en geen afgehandeld-knop meer', function () {
    $f = filamentReturn();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->assertActionVisible('process')
        ->assertActionVisible('close')
        ->assertActionVisible('reject')
        ->assertActionHidden('approve')
        ->assertActionHidden('registerRefund')
        ->assertActionDoesNotExist('markHandled');
});

it('verwerkt via de modal en koppelt de creditorder', function () {
    $f = filamentReturn();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->callAction('process', data: [
            'lines' => [$f['line']->id => 2],
            'restock' => false,
            'refund_discount' => false,
            'note' => 'Alles in orde',
        ])
        ->assertHasNoActionErrors();

    $return = $f['return']->fresh();
    expect($return->status)->toBe(OrderReturn::STATUS_HANDLED)
        ->and($return->credit_order_id)->not->toBeNull()
        ->and(Order::find($return->credit_order_id)->credit_for_order_id)->toBe($f['order']->id);
});

it('sluit via de modal met reden', function () {
    $f = filamentReturn();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->callAction('close', data: ['closed_reason' => 'Niets ontvangen'])
        ->assertHasNoActionErrors();

    expect($f['return']->fresh()->status)->toBe(OrderReturn::STATUS_CLOSED);
});

it('toont terugbetaling registreren alleen bij verwerkt zonder betaling en boekt hem', function () {
    $f = filamentReturn(OrderReturn::STATUS_HANDLED);
    $credit = Order::create(['email' => 'klant@example.com', 'status' => 'return', 'credit_for_order_id' => $f['order']->id, 'total' => -40, 'invoice_id' => 'CR-F']);
    $f['return']->update(['credit_order_id' => $credit->id]);

    $page = Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->assertActionVisible('registerRefund')
        ->assertActionHidden('process')
        ->callAction('registerRefund', data: ['amount' => 40, 'method' => 'Bankoverschrijving'])
        ->assertHasNoActionErrors();

    expect($f['return']->fresh()->isRefunded())->toBeTrue();

    Livewire::test(ViewOrderReturn::class, ['record' => $f['return']->id])
        ->assertActionHidden('registerRefund');
});

it('toont in de lijst de creditorder en het gecrediteerde bedrag', function () {
    $f = filamentReturn(OrderReturn::STATUS_HANDLED);
    $credit = Order::create(['email' => 'klant@example.com', 'status' => 'return', 'credit_for_order_id' => $f['order']->id, 'total' => -40, 'invoice_id' => 'CR-LIJST']);
    $f['return']->update(['credit_order_id' => $credit->id]);

    Livewire::test(ListOrderReturns::class)
        ->assertCanSeeTableRecords([$f['return']])
        ->assertSee('CR-LIJST')
        ->assertSee('40');
});
