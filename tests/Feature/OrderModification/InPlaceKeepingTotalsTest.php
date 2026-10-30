<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Support/InvoiceHarness.php';

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

// applyInPlace() maakt de factuur van een betaalde order opnieuw.
beforeEach(fn () => bootInvoiceHarness());

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

it('staat aanpassen in plaats toe bij een ander product met hetzelfde totaal', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    $ander = optieProduct('Tafel');
    $lines = [regelUit($line, ['product_id' => $ander->id, 'name' => 'Tafel'])];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $lines))->toBeTrue()
        ->and(OrderModificationService::changesStock($order, $lines))->toBeTrue();
});

it('staat aanpassen in plaats toe bij een verschil onder de tien cent', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, [regelUit($line, ['price' => 100.09])]))->toBeTrue()
        ->and(OrderModificationService::canModifyInPlaceKeepingTotals($order, [regelUit($line, ['price' => 99.91])]))->toBeTrue()
        ->and(OrderModificationService::canModifyInPlaceKeepingTotals($order, [regelUit($line, ['price' => 100.10])]))->toBeFalse()
        ->and(OrderModificationService::canModifyInPlaceKeepingTotals($order, [regelUit($line, ['price' => 99.90])]))->toBeFalse();
});

it('weigert twee regels die naar dezelfde bronregel wijzen', function () {
    [$order, $line] = betaaldeOrderMetOptie();

    $lines = [regelUit($line, ['price' => 50.0]), regelUit($line, ['price' => 50.0])];

    expect(OrderModificationService::canModifyInPlaceKeepingTotals($order, $lines))->toBeFalse();
});

it('ziet een omslaande voorraadvlag als voorraadwijziging', function () {
    [$order, $line, , , $blauw] = betaaldeOrderMetOptie();
    $blauw->update(['skip_stock' => true]);

    $lines = [regelUit($line, ['product_extras' => [
        ['id' => $blauw->id, 'name' => 'Kleur', 'value' => 'Blauw', 'path' => '', 'price' => 0.0],
    ]])];

    expect(OrderModificationService::changesStock($order, $lines))->toBeTrue();
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

it('ruilt een product met een cent verschil in plaats, zonder nieuwe betaling of betaaldatum', function () {
    [$order, $line, $stoel] = betaaldeOrderMetOptie();
    $stoel->update(['use_stock' => true, 'stock' => 5]);
    $tafel = optieProduct('Tafel');
    $tafel->update(['use_stock' => true, 'stock' => 5]);

    $aangemaakt = $order->created_at;
    $betalingen = $order->orderPayments()->get(['id', 'amount', 'updated_at'])->toArray();

    OrderModificationService::applyInPlace(
        $order,
        [regelUit($line, ['product_id' => $tafel->id, 'name' => 'Tafel', 'price' => 100.01, 'product_extras' => []])],
        ['send_customer_email' => false],
    );

    $order = $order->fresh();

    expect($order->replaced_by_order_id)->toBeNull()
        ->and(Order::count())->toBe(1)
        ->and($order->status)->toBe('paid')
        ->and(round((float) $order->total, 2))->toBe(100.0)
        ->and($order->orderProducts[0]->product_id)->toBe($tafel->id)
        ->and(round((float) $order->orderProducts[0]->price, 2))->toBe(100.0)
        ->and($order->created_at->equalTo($aangemaakt))->toBeTrue()
        ->and($order->orderPayments()->get(['id', 'amount', 'updated_at'])->toArray())->toBe($betalingen)
        ->and($stoel->fresh()->stock)->toBe(6)
        ->and($tafel->fresh()->stock)->toBe(4)
        ->and($order->logs()->where('tag', 'order.modified.small-difference')->exists())->toBeTrue();
});

it('laat de oude voorraad staan als de producten al verzonden zijn', function () {
    [$order, $line, $stoel] = betaaldeOrderMetOptie();
    $stoel->update(['use_stock' => true, 'stock' => 5]);
    $tafel = optieProduct('Tafel');
    $tafel->update(['use_stock' => true, 'stock' => 5]);

    OrderModificationService::applyInPlace(
        $order,
        [regelUit($line, ['product_id' => $tafel->id, 'name' => 'Tafel', 'product_extras' => []])],
        ['send_customer_email' => false, 'already_shipped' => true],
    );

    expect($stoel->fresh()->stock)->toBe(5)
        ->and($tafel->fresh()->stock)->toBe(4);
});
