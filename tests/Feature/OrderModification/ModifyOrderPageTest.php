<?php

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ModifyOrder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    $user = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($user);
});

function pageOrder(string $invoiceId = 'PROFORMA', string $status = 'paid'): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => $status,
        'invoice_id' => $invoiceId,
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);
    if ($status === 'paid') {
        $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);
    }

    return $order->fresh();
}

it('laadt de bestaande regels in het formulier', function () {
    $order = pageOrder();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->assertSet('data.lines.0.name', 'Oud product')
        ->assertSet('data.lines.0.quantity', 1);
});

it('maakt een vervangende order aan bij het opslaan van een betaalde order', function () {
    $order = pageOrder();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines', [
            ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 121.0, 'vat_rate' => 21],
        ])
        ->set('data.send_customer_email', false)
        ->call('submit');

    $order = $order->fresh();

    expect($order->replaced_by_order_id)->not->toBeNull()
        ->and(round($order->replacedByOrder->total, 2))->toBe(121.0);
});

it('past een concept in plaats aan bij het opslaan', function () {
    $order = pageOrder(invoiceId: 'PROFORMA', status: Order::STATUS_CONCEPT);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines', [
            ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 60.5, 'vat_rate' => 21],
        ])
        ->set('data.send_customer_email', false)
        ->call('submit');

    $order = $order->fresh();

    expect($order->replaced_by_order_id)->toBeNull()
        ->and(round($order->total, 2))->toBe(60.5)
        ->and($order->orderProducts()->first()->name)->toBe('Nieuw product');
});
