<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

/**
 * Productinzichten-endpoint (GET api/v1/insights/products?metric=...).
 *
 * top/slow/margin gebruiken lifetime `total_purchases` resp. statische
 * productvelden (goedkoop, geen periode-join). dead_stock joint daarentegen
 * wél order_products↔orders binnen de gekozen periode om producten te vinden
 * die in die periode 0 stuks verkochten maar wél voorraad hebben.
 */
function makeInsightProduct(array $overrides = []): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Group ' . uniqid()],
        'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Product ' . uniqid()],
        'slug' => ['en' => 'product-' . uniqid()],
        'site_ids' => ['site'],
        'use_stock' => true,
        'stock' => 10,
        'stock_status' => 'in_stock',
        'price' => 20,
        'current_price' => 20,
        'vat_rate' => 21,
        'total_purchases' => 0,
    ], $overrides)));
}

function makeInsightSale(Product $product, int $quantity, ?\Carbon\Carbon $when = null): void
{
    $order = Order::create([
        'first_name' => 'T', 'last_name' => 'K', 'email' => 'k@example.com',
        'hash' => Str::random(32), 'total' => 10, 'site_id' => 'site', 'ip' => '127.0.0.1',
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
    ]);
    if ($when) {
        $order->forceFill(['created_at' => $when])->saveQuietly();
    }

    $order->orderProducts()->create([
        'product_id' => $product->id,
        'name' => 'Regel',
        'quantity' => $quantity,
        'price' => 10,
    ]);
}

it('returns top sellers ordered by total_purchases desc', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $low = makeInsightProduct(['total_purchases' => 3]);
    $high = makeInsightProduct(['total_purchases' => 50]);
    $mid = makeInsightProduct(['total_purchases' => 20]);

    $response = $this->getJson('/api/v1/insights/products?metric=top', ['X-Site-Id' => 'site']);

    $response->assertStatus(200)
        ->assertJsonPath('metric', 'top');

    $ids = collect($response->json('items'))->pluck('id')->all();
    expect($ids)->toBe([$high->id, $mid->id, $low->id]);
    expect($response->json('items.0.sold'))->toBe(50);
    expect($response->json('items.0'))->toHaveKeys(['id', 'name', 'sold', 'current_price', 'stock']);
});

it('returns slow movers (lowest sellers with stock) ascending', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $slow = makeInsightProduct(['total_purchases' => 1, 'stock' => 5]);
    $fast = makeInsightProduct(['total_purchases' => 99, 'stock' => 5]);
    // Geen voorraad → niet in slow movers.
    makeInsightProduct(['total_purchases' => 0, 'stock' => 0]);

    $response = $this->getJson('/api/v1/insights/products?metric=slow', ['X-Site-Id' => 'site']);

    $response->assertStatus(200)->assertJsonPath('metric', 'slow');

    $ids = collect($response->json('items'))->pluck('id')->all();
    expect($ids[0])->toBe($slow->id);
    expect($ids)->toContain($fast->id);
    // Alleen producten met stock > 0.
    expect(count($ids))->toBe(2);
});

it('computes margin as current_price - purchase_price and skips null cost', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $a = makeInsightProduct(['price' => 30, 'current_price' => 30, 'purchase_price' => 10]); // margin 20
    $b = makeInsightProduct(['price' => 50, 'current_price' => 50, 'purchase_price' => 40]); // margin 10
    makeInsightProduct(['price' => 30, 'current_price' => 30, 'purchase_price' => null]); // skipped
    makeInsightProduct(['price' => 30, 'current_price' => 30, 'purchase_price' => 0]); // skipped

    $response = $this->getJson('/api/v1/insights/products?metric=margin', ['X-Site-Id' => 'site']);

    $response->assertStatus(200)->assertJsonPath('metric', 'margin');

    $items = collect($response->json('items'));
    expect($items)->toHaveCount(2);
    expect($items->pluck('id')->all())->toBe([$a->id, $b->id]);
    expect((float) $items[0]['margin'])->toBe(20.0);
    expect($items[0])->toHaveKeys(['purchase_price', 'margin', 'margin_pct']);
});

it('lists dead stock (stocked, zero in-period sales) and excludes products sold in period', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    $dead = makeInsightProduct(['stock' => 7]);
    $soldInPeriod = makeInsightProduct(['stock' => 7]);
    // Verkocht buiten de periode (vorig jaar) → telt als dead voor "month".
    $soldLongAgo = makeInsightProduct(['stock' => 7]);
    // Geen voorraad → nooit dead stock.
    makeInsightProduct(['stock' => 0]);

    makeInsightSale($soldInPeriod, 2, now());
    makeInsightSale($soldLongAgo, 2, now()->subYear());

    $response = $this->getJson('/api/v1/insights/products?metric=dead_stock&period=month', ['X-Site-Id' => 'site']);

    $response->assertStatus(200)->assertJsonPath('metric', 'dead_stock');

    $ids = collect($response->json('items'))->pluck('id')->all();
    expect($ids)->toContain($dead->id)
        ->and($ids)->toContain($soldLongAgo->id)
        ->and($ids)->not->toContain($soldInPeriod->id);
    expect($response->json('items.0'))->toHaveKeys(['id', 'name', 'stock', 'current_price']);
});

it('rejects insights without the dashboard.read ability', function () {
    $user = User::factory()->create(['role' => 'customer']);
    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/v1/insights/products?metric=top', ['X-Site-Id' => 'site'])
        ->assertStatus(403);
});
