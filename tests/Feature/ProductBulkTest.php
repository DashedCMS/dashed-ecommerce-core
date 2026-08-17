<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

/**
 * Save() dispatcht UpdateProductInformationJob (MySQL-only SQL in calculatePrices);
 * die faken we zodat de bulk-mutatie zelf op SQLite getest kan worden.
 */
function makeBulkProduct(array $over = []): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'product_group_id' => $group->id,
        'name' => ['en' => 'P-' . uniqid()],
        'slug' => ['en' => 'p-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
        'public' => true, 'use_stock' => true, 'stock' => 3,
        'images' => [],
    ], $over)));
}

function actAsAdmin(): void
{
    test()->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
}

it('zet meerdere producten in één keer op verborgen', function () {
    Queue::fake();
    actAsAdmin();
    $a = makeBulkProduct();
    $b = makeBulkProduct();

    $res = test()->postJson('/api/v1/products/bulk', [
        'ids' => [$a->id, $b->id], 'action' => 'visibility', 'value' => false,
    ], ['X-Site-Id' => 'site'])->assertSuccessful();

    expect($res->json('ok_count'))->toBe(2)->and($res->json('fail_count'))->toBe(0);
    expect((bool) $a->fresh()->public)->toBeFalse()
        ->and((bool) $b->fresh()->public)->toBeFalse();
});

it('zet de voorraad en de prijs in bulk', function () {
    Queue::fake();
    actAsAdmin();
    $a = makeBulkProduct();

    test()->postJson('/api/v1/products/bulk', [
        'ids' => [$a->id], 'action' => 'stock', 'value' => 25,
    ], ['X-Site-Id' => 'site'])->assertSuccessful();
    expect((int) $a->fresh()->stock)->toBe(25);

    test()->postJson('/api/v1/products/bulk', [
        'ids' => [$a->id], 'action' => 'price', 'value' => 9.5,
    ], ['X-Site-Id' => 'site'])->assertSuccessful();
    expect((float) $a->fresh()->price)->toBe(9.5);
});

it('wijst een categorie toe zonder bestaande te wissen', function () {
    Queue::fake();
    actAsAdmin();
    $a = makeBulkProduct();
    $cat = ProductCategory::create([
        'name' => ['en' => 'Cat'], 'slug' => ['en' => 'cat-' . uniqid()],
        'site_ids' => ['site'],
    ]);

    test()->postJson('/api/v1/products/bulk', [
        'ids' => [$a->id], 'action' => 'category', 'value' => $cat->id,
    ], ['X-Site-Id' => 'site'])->assertSuccessful();

    expect($a->fresh()->productCategories()->pluck('dashed__product_categories.id')->all())
        ->toContain($cat->id);
});

it('markeert onbekende / andere-site producten als mislukt', function () {
    Queue::fake();
    actAsAdmin();
    $a = makeBulkProduct();

    $res = test()->postJson('/api/v1/products/bulk', [
        'ids' => [$a->id, 999999], 'action' => 'visibility', 'value' => false,
    ], ['X-Site-Id' => 'site'])->assertSuccessful();

    expect($res->json('ok_count'))->toBe(1)->and($res->json('fail_count'))->toBe(1);
    $rows = collect($res->json('results'))->keyBy('id');
    expect($rows[$a->id]['ok'])->toBeTrue()
        ->and($rows[999999]['ok'])->toBeFalse();
});

it('vereist het products.write-recht', function () {
    Queue::fake();
    test()->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');

    test()->postJson('/api/v1/products/bulk', [
        'ids' => [1], 'action' => 'visibility', 'value' => false,
    ], ['X-Site-Id' => 'site'])->assertStatus(403);
});

it('weigert een ongeldige actie of voorraadwaarde', function () {
    Queue::fake();
    actAsAdmin();

    test()->postJson('/api/v1/products/bulk', [
        'ids' => [1], 'action' => 'explode', 'value' => 1,
    ], ['X-Site-Id' => 'site'])->assertStatus(422);

    test()->postJson('/api/v1/products/bulk', [
        'ids' => [1], 'action' => 'stock', 'value' => -5,
    ], ['X-Site-Id' => 'site'])->assertStatus(422);
});
