<?php

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function paidOrderWithoutRealInvoice(float $total = 100.0): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => 'PROFORMA',
        'total' => $total,
        'subtotal' => $total,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Oud product',
        'quantity' => 1,
        'price' => $total,
        'vat_rate' => 21,
    ]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => $total, 'psp' => 'own']);

    return $order->fresh();
}

function line(string $name, float $price, int $quantity = 1): array
{
    return [
        'order_product_id' => null,
        'product_id' => null,
        'name' => $name,
        'quantity' => $quantity,
        'price' => $price,
        'vat_rate' => 21,
        'product_extras' => [],
    ];
}

it('maakt een vervangende order met het verschil als openstaand bedrag', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Duurder product', 121.0)]);

    expect(round($new->total, 2))->toBe(121.0)
        ->and(round($new->outstandingAmount(), 2))->toBe(21.0)
        ->and($new->status)->toBe('partially_paid')
        ->and($new->orderProducts()->count())->toBe(1);
});

it('verplaatst de betalingen naar de vervangende order', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Duurder product', 121.0)]);

    expect($new->orderPayments()->where('status', 'paid')->count())->toBe(1)
        ->and($old->fresh()->orderPayments()->where('status', 'paid')->count())->toBe(0);
});

it('annuleert de oude order en koppelt hem aan de nieuwe', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Ander product', 100.0)]);

    expect($old->fresh()->status)->toBe('cancelled')
        ->and($old->fresh()->replaced_by_order_id)->toBe($new->id)
        ->and($new->fresh()->replacesOrder->id)->toBe($old->id)
        ->and($new->invoice_id)->not->toBe($old->invoice_id);
});

it('zet de vervangende order op betaald bij een gelijk totaal', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Ander product, zelfde prijs', 100.0)]);

    expect($new->status)->toBe('paid')
        ->and(round($new->outstandingAmount(), 2))->toBe(0.0)
        ->and(round($new->total, 2))->toBe(100.0);
});

it('laat een teveel betaald bedrag zien bij een lager totaal', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [line('Goedkoper product', 80.0)]);

    expect(round($new->overpaidAmount(), 2))->toBe(20.0)
        ->and($new->status)->toBe('paid');
});

it('zet geen herstelmails in de wachtrij voor de geannuleerde order', function () {
    $old = paidOrderWithoutRealInvoice(100.0);

    OrderModificationService::replaceWithNewOrder($old, [line('Ander product', 100.0)]);

    expect(AbandonedCartEmail::count())->toBe(0);
});

it('weigert een order te vervangen die al vervangen is', function () {
    $old = paidOrderWithoutRealInvoice(100.0);
    $old->replaced_by_order_id = Order::create(['email' => 'a@b.nl', 'status' => 'pending'])->id;
    $old->save();

    expect(fn () => OrderModificationService::replaceWithNewOrder($old, [line('Product', 100.0)]))
        ->toThrow(LogicException::class);
});
