<?php

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard\MostWishedProducts;

it('telt per product het aantal lijsten van de laatste 90 dagen', function () {
    $populair = Product::withoutEvents(fn () => Product::create(['name' => 'Populair', 'slug' => 'populair-'.Str::lower(Str::random(6)), 'price' => 20, 'current_price' => 20, 'public' => 1, 'site_ids' => ['default']]));
    $oud = Product::withoutEvents(fn () => Product::create(['name' => 'Oud', 'slug' => 'oud-'.Str::lower(Str::random(6)), 'price' => 20, 'current_price' => 20, 'public' => 1, 'site_ids' => ['default']]));

    foreach (range(1, 3) as $i) {
        Wishlist::create([])->items()->create(['product_id' => $populair->id, 'price_at_add' => 20]);
    }
    $item = Wishlist::create([])->items()->create(['product_id' => $oud->id, 'price_at_add' => 20]);
    DB::table('dashed__wishlist_items')->where('id', $item->id)->update([
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
    ]);

    $top = MostWishedProducts::top();

    expect($top->first()->name)->toBe('Populair')
        ->and((int) $top->first()->aantal)->toBe(3)
        ->and($top->pluck('name')->all())->not->toContain('Oud');
});
