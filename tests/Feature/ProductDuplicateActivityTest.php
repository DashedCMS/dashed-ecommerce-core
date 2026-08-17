<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

function makeDupProduct(): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Origineel'],
        'slug' => ['en' => 'origineel-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 12, 'current_price' => 12, 'vat_rate' => 21,
        'public' => true, 'use_stock' => true, 'stock' => 5, 'purchases' => 7,
        'images' => [],
    ]));
}

it('dupliceert een product tot een nieuw product met purchases 0 en een nieuw sku', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $source = makeDupProduct();

    $res = $this->postJson("/api/v1/products/{$source->id}/duplicate", [], ['X-Site-Id' => 'site'])
        ->assertSuccessful();

    $newId = $res->json('data.id');
    expect($newId)->not->toBe($source->id);

    $new = Product::find($newId);
    expect($new)->not->toBeNull()
        ->and((int) $new->purchases)->toBe(0)
        ->and($new->sku)->not->toBe($source->sku);
});

it('vereist het products.write-recht voor dupliceren', function () {
    Queue::fake();
    $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum');
    $source = makeDupProduct();

    $this->postJson("/api/v1/products/{$source->id}/duplicate", [], ['X-Site-Id' => 'site'])
        ->assertStatus(403);
});

it('geeft de mutatielog van een product terug', function () {
    Queue::fake();
    $me = User::factory()->create(['role' => 'admin']);
    $this->actingAs($me, 'sanctum');
    $source = makeDupProduct();

    activity()->performedOn($source)->causedBy($me)->withProperties(['stock' => 9])->log('mobile-api: product bijgewerkt');

    $res = $this->getJson("/api/v1/products/{$source->id}/activity", ['X-Site-Id' => 'site'])
        ->assertSuccessful();

    $rows = $res->json('data');
    expect($rows)->toBeArray()->and(count($rows))->toBeGreaterThan(0);
    expect(collect($rows)->pluck('description'))->toContain('mobile-api: product bijgewerkt');
});
