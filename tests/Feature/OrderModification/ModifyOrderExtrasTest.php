<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ModifyOrder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

function extrasPageProduct(string $naam = 'Stoel', float $prijs = 100.0): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => $naam], 'slug' => ['en' => 'extras-groep-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => $naam],
        'slug' => ['en' => 'extras-product-' . uniqid()],
        'site_ids' => ['site'],
        'price' => $prijs, 'current_price' => $prijs, 'vat_rate' => 21,
        'use_stock' => false, 'stock' => 0,
        'images' => [],
    ]));
}

/**
 * Een betaalde bestelling met één regel die een gekozen kleur draagt.
 * Betaald en met een echt factuurnummer, zodat de gewone in-plaats-route niet
 * geldt en de nieuwe schakelaar de enige weg is.
 *
 * @return array{0: Order, 1: OrderProduct, 2: Product, 3: ProductExtraOption, 4: ProductExtraOption}
 */
function extrasPageOrder(float $blauwPrijs = 0.0, bool $onlyOnce = false): array
{
    $product = extrasPageProduct();
    $extra = ProductExtra::create(['product_id' => $product->id, 'name' => 'Kleur', 'type' => 'single']);
    $rood = ProductExtraOption::create(['product_extra_id' => $extra->id, 'value' => 'Rood', 'price' => 0]);
    $blauw = ProductExtraOption::create([
        'product_extra_id' => $extra->id,
        'value' => 'Blauw',
        'price' => $blauwPrijs,
        'calculate_only_1_quantity' => $onlyOnce,
    ]);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'invoice_id' => '2026-0001',
        'total' => 100,
        'subtotal' => 100,
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
            ['id' => $rood->id, 'name' => 'Kleur', 'value' => 'Rood', 'path' => '', 'price' => 0.0],
        ],
    ]);

    return [$order->fresh(), $line->fresh(), $product, $rood, $blauw];
}

// ── De extra's staan in de formulierstaat ──────────────────────────────────

it('vult de gekozen optie in de formulierstaat', function () {
    [$order, , , $rood] = extrasPageOrder();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->assertSet('data.lines.0.product_extras.0.id', (string) $rood->id)
        ->assertSet('data.lines.0.product_extras.0.value', 'Rood');
});

// ── Een andere optie kiezen ────────────────────────────────────────────────

it('schrijft de nieuw gekozen optie weg', function () {
    [$order, , , , $blauw] = extrasPageOrder();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_extras.0.id', (string) $blauw->id)
        ->set('data.modify_in_place', true)
        ->set('data.send_customer_email', false)
        ->call('submit');

    $extras = $order->fresh()->orderProducts()->first()->product_extras;

    expect($order->fresh()->replaced_by_order_id)->toBeNull()
        ->and((int) $extras[0]['id'])->toBe($blauw->id)
        ->and($extras[0]['value'])->toBe('Blauw');
});

it('laat het regeltotaal meebewegen met het prijsverschil van de optie', function () {
    [$order, , , , $blauw] = extrasPageOrder(blauwPrijs: 15.0);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_extras.0.id', (string) $blauw->id)
        ->assertSet('data.lines.0.price', 115.0);
});

it('vermenigvuldigt het prijsverschil met het aantal', function () {
    [$order, $line, , , $blauw] = extrasPageOrder(blauwPrijs: 15.0);
    $line->update(['quantity' => 3, 'price' => 300]);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_extras.0.id', (string) $blauw->id)
        ->assertSet('data.lines.0.price', 345.0);
});

it('rekent een optie met calculate_only_1_quantity maar een keer', function () {
    [$order, $line, , , $blauw] = extrasPageOrder(blauwPrijs: 15.0, onlyOnce: true);
    $line->update(['quantity' => 3, 'price' => 300]);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_extras.0.id', (string) $blauw->id)
        ->assertSet('data.lines.0.price', 315.0);
});

// ── Een ander product op de regel ──────────────────────────────────────────

it('leegt de extras zodra er een ander product op de regel komt', function () {
    [$order] = extrasPageOrder();
    $ander = extrasPageProduct('Tafel', 100.0);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_id', $ander->id)
        ->assertSet('data.lines.0.product_extras', []);
});

// ── De schakelaar ──────────────────────────────────────────────────────────

it('maakt een vervangende bestelling wanneer de schakelaar uit blijft', function () {
    [$order, , , , $blauw] = extrasPageOrder();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_extras.0.id', (string) $blauw->id)
        ->set('data.send_customer_email', false)
        ->call('submit');

    expect($order->fresh()->replaced_by_order_id)->not->toBeNull();
});

it('weigert de schakelaar wanneer het bedrag wel verandert', function () {
    [$order, , , , $blauw] = extrasPageOrder(blauwPrijs: 15.0);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_extras.0.id', (string) $blauw->id)
        ->set('data.modify_in_place', true)
        ->set('data.send_customer_email', false)
        ->call('submit');

    // Niet in plaats aangepast en ook niet geklapt: het scherm valt terug op
    // een nette melding en laat de bestelling met rust.
    expect($order->fresh()->replaced_by_order_id)->toBeNull()
        ->and($order->fresh()->orderProducts()->first()->product_extras[0]['value'])->toBe('Rood');
});
