<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;

/**
 * Concept-orders (onbetaald, geen factuurnummer) mogen niet in de
 * "Openstaande bestellingen" verschijnen of meetellen. Pas als een order
 * betaald is of een echt factuurnummer heeft, telt hij mee.
 */
function openOrderLineIds(): array
{
    return OpenOrderProductResource::getEloquentQuery()
        ->pluck('dashed__order_products.id')
        ->all();
}

it('includes an unhandled order with a real invoice number', function () {
    $order = Order::create(['email' => 'a@b.nl', 'invoice_id' => 'INV-100', 'status' => 'paid', 'total' => 10]);
    $order->fulfillment_status = 'unhandled';
    $order->save();
    $line = $order->orderProducts()->create(['product_id' => null, 'name' => 'Echt', 'quantity' => 1, 'price' => 10]);

    expect(openOrderLineIds())->toContain($line->id);
});

it('excludes a concept order without an invoice number', function () {
    $order = Order::create(['email' => 'c@b.nl', 'status' => Order::STATUS_CONCEPT, 'total' => 10]);
    $order->fulfillment_status = 'unhandled';
    $order->save();
    $line = $order->orderProducts()->create(['product_id' => null, 'name' => 'Concept', 'quantity' => 1, 'price' => 10]);

    expect(openOrderLineIds())->not->toContain($line->id);
});

it('excludes an unpaid proforma still on the PROFORMA placeholder invoice', function () {
    $order = Order::create(['email' => 'p@b.nl', 'invoice_id' => 'PROFORMA', 'status' => 'pending', 'is_proforma' => true, 'total' => 10]);
    $order->fulfillment_status = 'unhandled';
    $order->save();
    $line = $order->orderProducts()->create(['product_id' => null, 'name' => 'Proforma', 'quantity' => 1, 'price' => 10]);

    expect(openOrderLineIds())->not->toContain($line->id);
});
