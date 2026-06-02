<?php

use App\Models\User;
use Livewire\Livewire;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages\ListOpenOrderProducts;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function openOrderProductFixtures(): array
{
    $order = Order::create([
        'invoice_id' => 'INV-'.uniqid(),
        'status' => 'paid',
        'order_origin' => 'own',
        'total' => 10,
    ]);
    // De resource toont alleen regels van orders met fulfillment_status 'unhandled'.
    $order->fulfillment_status = 'unhandled';
    $order->save();

    // product_id is een echte foreign key, dus we hebben een bestaand product nodig.
    $productGroup = ProductGroup::create([
        'name' => ['en' => 'Groep'],
        'slug' => ['en' => 'groep-'.uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);
    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $productGroup->id,
        'name' => ['en' => 'P'],
        'slug' => ['en' => 'p-'.uniqid()],
        'site_ids' => ['default'],
    ]));

    $withId = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Met ID',
        'quantity' => 1,
    ]);
    $withoutId = OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => null,
        'name' => 'Zonder ID',
        'quantity' => 1,
    ]);

    return [$withId, $withoutId];
}

it('shows only products with a product_id when filter is true', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    [$withId, $withoutId] = openOrderProductFixtures();

    Livewire::test(ListOpenOrderProducts::class)
        ->filterTable('has_product_id', true)
        ->assertCanSeeTableRecords([$withId])
        ->assertCanNotSeeTableRecords([$withoutId]);
});

it('shows only products without a product_id when filter is false', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    [$withId, $withoutId] = openOrderProductFixtures();

    Livewire::test(ListOpenOrderProducts::class)
        ->filterTable('has_product_id', false)
        ->assertCanSeeTableRecords([$withoutId])
        ->assertCanNotSeeTableRecords([$withId]);
});

it('shows both when the product_id filter is not set', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    [$withId, $withoutId] = openOrderProductFixtures();

    Livewire::test(ListOpenOrderProducts::class)
        ->assertCanSeeTableRecords([$withId, $withoutId]);
});
