<?php

use Illuminate\Support\Facades\Mail;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Mail\OrderModifiedMail;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function mailableOrder(): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => 'PROFORMA',
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    return $order->fresh();
}

function mailLine(float $price): array
{
    return ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => $price, 'vat_rate' => 21, 'product_extras' => []];
}

it('stuurt standaard een wijzigingsmail naar de klant', function () {
    Mail::fake();
    $old = mailableOrder();

    OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)]);

    Mail::assertSent(OrderModifiedMail::class, fn ($mail) => $mail->hasTo('klant@example.com'));
});

it('stuurt geen mail wanneer die onderdrukt is', function () {
    Mail::fake();
    $old = mailableOrder();

    OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['send_customer_email' => false]);

    Mail::assertNothingSent();
});

it('neemt de toelichting mee in de mail', function () {
    Mail::fake();
    $old = mailableOrder();

    OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['customer_note' => 'Het blauwe model was op.']);

    Mail::assertSent(OrderModifiedMail::class, fn ($mail) => $mail->note === 'Het blauwe model was op.');
});

it('rendert het openstaande bedrag en de betaallink in de mail', function () {
    $old = mailableOrder();
    $new = OrderModificationService::replaceWithNewOrder($old, [mailLine(121.0)], ['send_customer_email' => false]);

    $rendered = (new OrderModifiedMail($new, null))->render();

    expect($rendered)->toContain('Nieuw product')
        ->and($rendered)->toContain('/pay/order/' . $new->hash . '/remainder');
});
