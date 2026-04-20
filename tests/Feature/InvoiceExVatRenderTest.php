<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

function makeInvoiceOrder(bool $pricesExVat, bool $reverseCharge = false): Order {
    $order = Order::create([
        'status' => 'paid',
        'prices_ex_vat' => $pricesExVat,
        'vat_reverse_charge' => $reverseCharge,
        'subtotal' => 121.00,
        'btw' => 21.00,
        'total' => 121.00,
        'discount' => 0,
        'vat_percentages' => ['21' => 21.00],
    ]);

    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Test',
        'quantity' => 1,
        'price' => 121.00,
        'vat_rate' => 21,
    ]);

    return $order->fresh();
}

it('renders ex-BTW subtotal and bold totaal incl when prices_ex_vat is true', function () {
    $order = makeInvoiceOrder(pricesExVat: true);

    $html = view('dashed-ecommerce-core::invoices.invoice', ['order' => $order])->render();

    expect($html)->toContain('Subtotaal ex BTW');
    expect($html)->toContain('Totaal incl');
    expect($html)->toContain('100,'); // 100,- or 100,00 — ex amount for 121 incl @ 21%
});

it('renders the incl-BTW layout when prices_ex_vat is false', function () {
    $order = makeInvoiceOrder(pricesExVat: false);

    $html = view('dashed-ecommerce-core::invoices.invoice', ['order' => $order])->render();

    expect($html)->not->toContain('Subtotaal ex BTW');
});

it('falls back to incl-mode layout when vat_reverse_charge is true, regardless of prices_ex_vat', function () {
    $order = makeInvoiceOrder(pricesExVat: true, reverseCharge: true);

    $html = view('dashed-ecommerce-core::invoices.invoice', ['order' => $order])->render();

    expect($html)->not->toContain('Subtotaal ex BTW');
});
