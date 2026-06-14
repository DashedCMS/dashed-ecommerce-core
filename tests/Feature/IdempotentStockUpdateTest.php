<?php

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProcessedOperation;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;

/**
 * De offline-scan-wachtrij in de app stuurt voorraad-mutaties met een
 * client-gegenereerd `op_id`. Bij een replay (bv. dubbele sync) mag de
 * voorraad maar één keer veranderen. Zonder op_id blijft het gedrag ongewijzigd.
 */
function makeStockProduct(int $stock = 10): Product
{
    // UpdateProductInformationJob gebruikt MySQL-only SQL in tests; faken zodat
    // de save-hook geen SQLite-fout geeft (zie ProductWriteTest).
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Stock product'],
        'slug' => ['en' => 'stock-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
        'use_stock' => true, 'stock' => $stock,
        'images' => [],
    ]));
}

it('applies a stock update with an op_id only once on replay', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $product = makeStockProduct(10);

    $opId = 'op-' . uniqid();

    // Eerste keer: voorraad wordt op 25 gezet.
    $first = $this->putJson("/api/v1/products/{$product->id}", [
        'stock' => 25,
        'op_id' => $opId,
    ], ['X-Site-Id' => 'site']);
    $first->assertStatus(200);
    expect((int) $first->json('data.stock'))->toBe(25);
    expect((int) $product->fresh()->stock)->toBe(25);

    // Replay met hetzelfde op_id maar een andere stock-waarde: mag NIET opnieuw
    // toepassen — voorraad blijft 25 en het antwoord spiegelt het eerste resultaat.
    $second = $this->putJson("/api/v1/products/{$product->id}", [
        'stock' => 999,
        'op_id' => $opId,
    ], ['X-Site-Id' => 'site']);
    $second->assertStatus(200);
    expect((int) $product->fresh()->stock)->toBe(25);

    // Er staat precies één logboekregel voor dit op_id.
    expect(ProcessedOperation::where('op_id', $opId)->count())->toBe(1);
});

it('applies every stock update when no op_id is provided', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');
    $product = makeStockProduct(10);

    $this->putJson("/api/v1/products/{$product->id}", ['stock' => 30], ['X-Site-Id' => 'site'])
        ->assertStatus(200);
    expect((int) $product->fresh()->stock)->toBe(30);

    $this->putJson("/api/v1/products/{$product->id}", ['stock' => 40], ['X-Site-Id' => 'site'])
        ->assertStatus(200);
    expect((int) $product->fresh()->stock)->toBe(40);

    expect(ProcessedOperation::count())->toBe(0);
});
