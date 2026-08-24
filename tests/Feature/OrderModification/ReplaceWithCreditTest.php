<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlowStep;
use Dashed\DashedEcommerceCore\Mail\AdminOrderCancelledMail;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Zelfde patroon als tests/Feature/IdempotentStockUpdateTest.php: translatable
 * name en slug, save-hook uit en queue gefaket.
 */
function creditStockedProduct(int $stock = 5): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Voorraadproduct'],
        'slug' => ['en' => 'voorraad-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 100, 'current_price' => 100, 'vat_rate' => 21,
        'use_stock' => true, 'stock' => $stock,
        'images' => [],
    ]));
}

function invoicedPaidOrder(float $total = 100.0, ?Product $product = null, int $quantity = 1): Order
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => '2026-0001',
        'total' => $total,
        'subtotal' => $total,
    ]);
    OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product?->id,
        'name' => $product ? 'Voorraadproduct' : 'Oud product',
        'quantity' => $quantity,
        'price' => $total,
        'vat_rate' => 21,
    ]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => $total, 'psp' => 'own']);

    return $order->fresh();
}

function creditLine(string $name, float $price, int $quantity = 1): array
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

it('crediteert de oude order en laat hem op betaald staan', function () {
    $old = invoicedPaidOrder(100.0);

    OrderModificationService::replaceWithNewOrder($old, [creditLine('Duurder product', 121.0)]);

    $old = $old->fresh();

    expect($old->status)->toBe('paid')
        ->and($old->orderPayments()->where('status', 'paid')->count())->toBe(1)
        ->and(Order::where('credit_for_order_id', $old->id)->count())->toBe(1);
});

it('verrekent het betaalde bedrag met tegenboekingen', function () {
    $old = invoicedPaidOrder(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [creditLine('Duurder product', 121.0)]);

    $creditOrder = Order::where('credit_for_order_id', $old->id)->first();

    expect(round($new->outstandingAmount(), 2))->toBe(21.0)
        ->and($new->status)->toBe('partially_paid')
        ->and(round((float) $new->orderPayments()->where('status', 'paid')->sum('amount'), 2))->toBe(100.0)
        ->and(round((float) $creditOrder->orderPayments()->where('status', 'paid')->sum('amount'), 2))->toBe(round((float) $creditOrder->total, 2));
});

it('verrekent alleen het werkelijk betaalde bedrag bij een deels betaalde factuur', function () {
    $old = invoicedPaidOrder(100.0);
    $old->orderPayments()->delete();
    $old->orderPayments()->create(['status' => 'paid', 'amount' => 60, 'psp' => 'own']);
    $old->status = 'partially_paid';
    $old->save();

    $new = OrderModificationService::replaceWithNewOrder($old->fresh(), [creditLine('Ander product', 100.0)]);

    $allPaid = Order::query()->get()->sum(
        fn (Order $order) => (float) $order->orderPayments()->where('status', 'paid')->sum('amount')
    );

    expect(round($allPaid, 2))->toBe(60.0)
        ->and(round((float) $new->orderPayments()->where('status', 'paid')->sum('amount'), 2))->toBe(60.0)
        ->and(round($new->outstandingAmount(), 2))->toBe(40.0);
});

it('houdt de som van alle betalingen gelijk aan het nieuwe totaal', function () {
    $old = invoicedPaidOrder(100.0);

    $new = OrderModificationService::replaceWithNewOrder($old, [creditLine('Ander product, zelfde prijs', 100.0)]);

    $allPaid = Order::query()->get()->sum(
        fn (Order $order) => (float) $order->orderPayments()->where('status', 'paid')->sum('amount')
    );

    expect(round($allPaid, 2))->toBe(100.0)
        ->and($new->status)->toBe('paid');
});

it('boekt de voorraad netto om', function () {
    $product = creditStockedProduct(5);
    $old = invoicedPaidOrder(100.0, $product, 2);

    OrderModificationService::replaceWithNewOrder($old, [
        ['order_product_id' => null, 'product_id' => $product->id, 'name' => 'Voorraadproduct', 'quantity' => 1, 'price' => 100.0, 'vat_rate' => 21, 'product_extras' => []],
    ]);

    // 5 + 2 terug van de credit, 1 er weer af voor de nieuwe order.
    expect($product->fresh()->stock)->toBe(6);
});

it('laat de voorraad staan wanneer de producten al verzonden zijn', function () {
    $product = creditStockedProduct(5);
    $old = invoicedPaidOrder(100.0, $product, 2);

    OrderModificationService::replaceWithNewOrder($old, [
        ['order_product_id' => null, 'product_id' => $product->id, 'name' => 'Voorraadproduct', 'quantity' => 1, 'price' => 100.0, 'vat_rate' => 21, 'product_extras' => []],
    ], ['already_shipped' => true]);

    // Niets terug, alleen de nieuwe order boekt af.
    expect($product->fresh()->stock)->toBe(4);
});

it('zet de retourstatus wanneer de producten terug moeten komen', function () {
    $old = invoicedPaidOrder(100.0);

    OrderModificationService::replaceWithNewOrder($old, [creditLine('Ander product', 100.0)], ['products_must_be_returned' => true]);

    $creditOrder = Order::where('credit_for_order_id', $old->id)->first();

    expect($old->fresh()->retour_status)->toBe('waiting_for_return')
        ->and($creditOrder->retour_status)->toBe('waiting_for_return');
});

it('schrijft de klant in de credittak niet in voor de na-aankoop-flows', function () {
    // De oude toets hier keek naar AbandonedCartEmail. Die kon niet falen: in
    // de credittak blijven de betaalde betalingen op de oude order staan en
    // QueueAbandonedCartEmailsForOrderListener stapt per definitie uit zodra
    // een order een betaalde betaling heeft, ongeacht wat deze code doet.
    //
    // Wat hier wél op het spel staat is de andere flow. De credittak riep
    // changeFulfillmentStatus('handled') aan op een order die op 'paid' blijft
    // staan mét echt factuurnummer, en QueueOrderFlowEmailsListener schreef de
    // klant daarmee in voor de na-aankoop-opvolging van een bestelling die net
    // weggecrediteerd is. Deze toets faalt zodra dat terugkomt.
    $flow = OrderHandledFlow::create([
        'name' => 'Na aankoop', 'is_active' => true,
        'trigger_status' => 'handled', 'discount_prefix' => 'N',
    ]);
    OrderHandledFlowStep::create([
        'flow_id' => $flow->id, 'sort_order' => 1,
        'send_after_minutes' => 60, 'is_active' => true,
        'subject' => 'Hoe bevalt je bestelling?',
        'blocks' => [['type' => 'text', 'data' => ['content' => '<p>Hoi</p>']]],
    ]);

    $old = invoicedPaidOrder(100.0);
    $old->fulfillment_status = 'shipped';
    $old->save();

    OrderModificationService::replaceWithNewOrder($old->fresh(), [creditLine('Ander product', 100.0)]);

    expect(OrderFlowEnrollment::count())->toBe(0)
        // De oude order komt wél op 'handled' te staan: hij is vervangen en
        // hoeft niet meer opgepakt te worden. Dat gebeurt stil, buiten
        // changeFulfillmentStatus() om, en juist dat maakt deze toets scherper
        // dan hij was. De status verzet zich naar precies de waarde waar deze
        // flow op triggert, en er mag nog steeds geen inschrijving ontstaan.
        ->and($old->fresh()->fulfillment_status)->toBe('handled');
});

it('stuurt de beheerders geen annuleringsmail bij een wijziging via de credittak', function () {
    Mail::fake();

    $old = invoicedPaidOrder(100.0);

    OrderModificationService::replaceWithNewOrder($old, [creditLine('Ander product', 100.0)], ['send_customer_email' => false]);

    Mail::assertNotSent(AdminOrderCancelledMail::class);
});
