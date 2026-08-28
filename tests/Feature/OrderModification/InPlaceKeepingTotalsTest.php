<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Classes\OrderModificationService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * Een betaalde bestelling met een echte factuur: de automatische in-plaats-route
 * geldt hier niet, dus alleen de nieuwe route kan hem toelaten.
 */
function optieProduct(string $naam): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => $naam], 'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => $naam],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 100, 'current_price' => 100, 'vat_rate' => 21,
        'use_stock' => false, 'stock' => 0,
        'images' => [],
    ]));
}

function betaaldeOrderMetOptie(array $optieOverschrijving = []): array
{
    Customsetting::set('taxes_prices_include_taxes', 1);

    $product = optieProduct('Stoel');
    $extra = ProductExtra::create(['product_id' => $product->id, 'name' => 'Kleur', 'type' => 'single']);
    $rood = ProductExtraOption::create(array_merge(['product_extra_id' => $extra->id, 'value' => 'Rood', 'price' => 0], $optieOverschrijving));
    $blauw = ProductExtraOption::create(array_merge(['product_extra_id' => $extra->id, 'value' => 'Blauw', 'price' => 0], $optieOverschrijving));

    $order = Order::create([
        'email' => 'a@b.nl',
        'status' => 'paid',
        'invoice_id' => '2026-0001',
        'total' => 100,
        'subtotal' => 100,
        'site_id' => 'site',
    ]);
    $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);

    $line = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Stoel',
        'quantity' => 1,
        'price' => 100,
        'vat_rate' => 21,
        'product_extras' => [
            ['id' => $rood->id, 'name' => 'Kleur', 'value' => 'Rood', 'path' => '', 'price' => (float) $rood->price],
        ],
    ]);

    return [$order->fresh(), $line->fresh(), $product, $rood, $blauw];
}

function regelUit(OrderProduct $line, array $overschrijving = []): array
{
    return array_merge([
        'order_product_id' => $line->id,
        'product_id' => $line->product_id,
        'name' => $line->name,
        'quantity' => (int) $line->quantity,
        'price' => (float) $line->price,
        'vat_rate' => (float) $line->vat_rate,
        'product_extras' => $line->product_extras ?? [],
    ], $overschrijving);
}

// ── De guard ───────────────────────────────────────────────────────────────

it('laat een betaalde order niet toe via de gewone route', function () {
    [$order] = betaaldeOrderMetOptie();

    expect(OrderModificationService::canModifyInPlace($order))->toBeFalse();
});

it('staat aanpassen in plaats toe bij enkel een optiewissel', function () {
    [$order, $line, , , $blauw] = betaaldeOrderMetOptie();

    $lines = [regelUit($line, ['product_extras' => [
        ['id' => $blauw->id, 'name' => 'Kleur', 'value' => 'Blauw', 'path' => '', 'price' => 0.0],
    ]])];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $lines))->toBeTrue();
});

it('weigert aanpassen in plaats zodra het totaal verandert', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    $lines = [regelUit($line, ['price' => 120.0])];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $lines))->toBeFalse();
});

it('weigert aanpassen in plaats zodra een aantal verandert', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    // Prijs gelijk gehouden, alleen het aantal verandert: financieel neutraal,
    // maar de voorraad is voor een ander aantal afgeboekt.
    $lines = [regelUit($line, ['quantity' => 2])];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $lines))->toBeFalse();
});

it('weigert aanpassen in plaats zodra er een ander product op de regel staat', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    $ander = optieProduct('Tafel');
    $lines = [regelUit($line, ['product_id' => $ander->id])];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $lines))->toBeFalse();
});

it('weigert aanpassen in plaats zodra een regel erbij komt of weggaat', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    $splitsing = [
        regelUit($line, ['price' => 60.0]),
        ['order_product_id' => null, 'product_id' => null, 'name' => 'Toeslag', 'quantity' => 1, 'price' => 40.0, 'vat_rate' => 21, 'product_extras' => []],
    ];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $splitsing))->toBeFalse();
});

it('weigert aanpassen in plaats zodra de voorraadvlag van een optie omslaat', function () {
    // Rood boekt voorraad af, blauw niet. Het bedrag blijft gelijk, maar de
    // voorraad is wel afgeboekt en applyInPlace() boekt niets terug.
    [$order, $line, , , $blauw] = betaaldeOrderMetOptie();
    $blauw->update(['skip_stock' => true]);

    $lines = [regelUit($line, ['product_extras' => [
        ['id' => $blauw->id, 'name' => 'Kleur', 'value' => 'Blauw', 'path' => '', 'price' => 0.0],
    ]])];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $lines))->toBeFalse();
});

it('weigert aanpassen in plaats op een al vervangen order', function () {
    [$order, $line] = betaaldeOrderMetOptie();
    $vervanger = Order::create(['email' => 'a@b.nl', 'status' => 'pending', 'site_id' => 'site']);
    $order->update(['replaced_by_order_id' => $vervanger->id]);

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order->fresh(), [regelUit($line)]))->toBeFalse();
});

// ── Doorvoeren ─────────────────────────────────────────────────────────────

it('voert een optiewissel op een betaalde order in plaats door', function () {
    [$order, $line, , , $blauw] = betaaldeOrderMetOptie();

    $lines = [regelUit($line, ['product_extras' => [
        ['id' => $blauw->id, 'name' => 'Kleur', 'value' => 'Blauw', 'path' => '', 'price' => 0.0],
    ]])];

    OrderModificationService::applyInPlace($order, $lines, ['send_customer_email' => false]);

    $order = $order->fresh();

    expect($order->replaced_by_order_id)->toBeNull()
        ->and($order->orderProducts)->toHaveCount(1)
        ->and($order->orderProducts[0]->product_extras[0]['value'])->toBe('Blauw')
        ->and((int) $order->orderProducts[0]->product_extras[0]['id'])->toBe($blauw->id)
        ->and(round((float) $order->total, 2))->toBe(100.0);
});

it('weigert doorvoeren wanneer de regels niet aan de voorwaarden voldoen', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    OrderModificationService::applyInPlace($order, [regelUit($line, ['price' => 120.0])]);
})->throws(LogicException::class);
