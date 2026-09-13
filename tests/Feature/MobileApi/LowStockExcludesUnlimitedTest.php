<?php

use Illuminate\Support\Facades\Queue;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;

/**
 * Lage-voorraad-selectie telt alleen producten die écht op raken. Producten op
 * "oneindig doorverkopen" (out_of_stock_sellable) raken nooit op en horen er
 * dus niet in, ook al staat hun voorraad op/onder de drempel. Dezelfde
 * where-voorwaarde als de app-lijst (ProductController@index) en de
 * lage-voorraad-melding (CheckLowStock).
 */
beforeEach(fn () => Queue::fake());

function lowStockProduct(string $name, bool $unlimited, int $stock): Product
{
    $group = ProductGroup::create([
        'name' => ['nl' => $name . ' groep'],
        'slug' => ['nl' => 'g-' . uniqid()],
        'short_description' => ['nl' => ''], 'description' => ['nl' => ''],
        'content' => ['nl' => ''], 'search_terms' => ['nl' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(function () use ($group, $name, $unlimited, $stock) {
        $product = Product::create([
            'product_group_id' => $group->id,
            'name' => ['nl' => $name],
            'slug' => ['nl' => 's-' . uniqid()],
            'site_ids' => ['site'],
            'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
        ]);

        // Voorraadvelden expliciet zetten (niet mass-assignable).
        $product->forceFill([
            'use_stock' => true,
            'out_of_stock_sellable' => $unlimited,
            'stock' => $stock,
            'low_stock_notification_limit' => 5,
        ])->save();

        return $product;
    });
}

it('lage voorraad: sluit producten op oneindig doorverkopen uit', function () {
    $laag = lowStockProduct('Bijna op', false, 1);
    $oneindig = lowStockProduct('Oneindig door', true, 0);

    // Exact de where-voorwaarde die ProductController@index (low_stock=1) en
    // CheckLowStock gebruiken.
    $ids = Product::query()
        ->where('use_stock', true)
        ->where('out_of_stock_sellable', false)
        ->where(function ($q): void {
            $q->whereColumn('stock', '<=', 'low_stock_notification_limit')
                ->orWhere('stock', '<=', 0);
        })
        ->pluck('id');

    expect($ids)->toContain($laag->id)->not->toContain($oneindig->id);
});
