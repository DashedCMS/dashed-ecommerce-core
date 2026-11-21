<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

/**
 * Openstaande bestellingen in de app: sorteren en filteren op de combinatie van
 * voorraad bij bestellen en voorraad nu. Voorraad nu wordt per regel bepaald
 * door de fysieke voorraad oudste-bestelling-eerst over de open regels te verdelen.
 */
function stockComboProduct(string $name, array $overrides = []): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => $name], 'slug' => ['en' => Str::slug($name) . '-g'],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''], 'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'name' => ['en' => $name], 'slug' => ['en' => Str::slug($name)], 'site_ids' => ['site'],
        'product_group_id' => $group->id, 'use_stock' => true, 'stock' => 0, 'total_stock' => 0,
        'in_stock' => true, 'stock_status' => 'in_stock', 'price' => 10, 'current_price' => 10,
    ], $overrides)));
}

function stockComboLine(string $name, ?Product $product, int $qty, ?string $atOrder, string $createdAt): OrderProduct
{
    $order = Order::create([
        'site_id' => 'site', 'email' => 'klant@example.com', 'invoice_id' => 'SC-' . strtoupper(uniqid()),
        'status' => 'paid', 'fulfillment_status' => 'unhandled',
    ]);
    Order::query()->whereKey($order->id)->update(['created_at' => $createdAt]);

    $line = OrderProduct::withoutEvents(fn () => OrderProduct::create([
        'order_id' => $order->id, 'product_id' => $product?->id, 'name' => $name,
        'sku' => 'SKU-' . strtoupper(uniqid()), 'quantity' => $qty, 'price' => 10,
    ]));
    DB::table('dashed__order_products')->where('id', $line->id)->update(['stock_status_at_order' => $atOrder]);

    return $line;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    // A: 2 + 1 open en al afgeboekt, voorraad nu -1 → fysiek 2: oudste regel gedekt, nieuwste niet.
    $a = stockComboProduct('Alpha', ['stock' => -1]);
    stockComboLine('A oud', $a, 2, 'in_stock', '2026-11-01 10:00:00');
    stockComboLine('A nieuw', $a, 1, 'out_of_stock', '2026-11-03 10:00:00');
    // B: backorder, inmiddels aangevuld tot 0 → fysiek 1, regel gedekt.
    stockComboLine('B', stockComboProduct('Beta', ['stock' => 0]), 1, 'out_of_stock', '2026-11-02 10:00:00');
    // C: geen voorraadbeheer, handmatig uitverkocht.
    stockComboLine('C', stockComboProduct('Gamma', ['use_stock' => false, 'stock_status' => 'out_of_stock']), 1, 'in_stock', '2026-11-04 10:00:00');
    // Losse regel zonder product of status → onbekend.
    stockComboLine('Los', null, 1, null, '2026-10-01 10:00:00');
});

it('open-order-products: geeft de voorraadstatus nu per regel terug (oudste eerst gedekt)', function () {
    $data = collect($this->getJson('/api/v1/open-order-products', ['X-Site-Id' => 'site'])->json('data'))
        ->mapWithKeys(fn ($r) => [$r['name'] => $r['stock_status_now']]);

    expect($data['A oud'])->toBe('in_stock')
        ->and($data['A nieuw'])->toBe('out_of_stock')
        ->and($data['B'])->toBe('in_stock')
        ->and($data['C'])->toBe('out_of_stock')
        ->and($data['Los'])->toBeNull();
});

it('open-order-products: sorteert op voorraad bij bestellen / nu, onbekend achteraan', function () {
    $names = collect($this->getJson('/api/v1/open-order-products?sort=stock_combo&direction=asc', ['X-Site-Id' => 'site'])->json('data'))
        ->pluck('name')->all();

    expect($names)->toBe(['B', 'A oud', 'C', 'A nieuw', 'Los']);
});

it('open-order-products: filtert op combinaties van voorraad bij bestellen / nu', function () {
    $names = fn (string $combo) => collect($this->getJson('/api/v1/open-order-products?sort=stock_combo&stock_combo=' . $combo, ['X-Site-Id' => 'site'])->json('data'))
        ->pluck('name')->all();

    expect($names('out_in'))->toBe(['B'])
        ->and($names('in_in'))->toBe(['A oud'])
        ->and($names('in_out'))->toBe(['C'])
        ->and($names('out_out'))->toBe(['A nieuw'])
        ->and($names('out_in,in_out'))->toBe(['B', 'C']);
});
