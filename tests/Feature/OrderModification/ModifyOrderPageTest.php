<?php

use Livewire\Livewire;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ModifyOrder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Customsetting::set('taxes_prices_include_taxes', 1);
    $user = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($user);
});

function pageOrder(string $invoiceId = 'PROFORMA', string $status = 'paid'): Order
{
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => $status,
        'invoice_id' => $invoiceId,
        'total' => 100,
        'subtotal' => 100,
    ]);
    OrderProduct::create(['order_id' => $order->id, 'name' => 'Oud product', 'quantity' => 1, 'price' => 100, 'vat_rate' => 21]);
    if ($status === 'paid') {
        $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own']);
    }

    return $order->fresh();
}

/**
 * Zelfde patroon als tests/Feature/OrderModification/CancelRestockOptionTest.php:
 * name/slug zijn translatable arrays, model-events staan uit en de queue is
 * gefaket omdat UpdateProductInformationJob MySQL-only SQL gebruikt. Namen
 * bewust anders dan die van de andere OrderModification-testbestanden: alle
 * bestanden in deze map draaien in hetzelfde PHP-proces en globale functies
 * mogen dus niet botsen.
 */
function modifyPageProduct(float $price = 10.0, bool $useStock = false, int $stock = 0): Product
{
    Queue::fake();

    $group = ProductGroup::create([
        'name' => ['en' => 'Group'], 'slug' => ['en' => 'group-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
    ]);

    return Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Testproduct'],
        'slug' => ['en' => 'test-' . uniqid()],
        'site_ids' => ['site'],
        'price' => $price, 'current_price' => $price, 'vat_rate' => 21,
        'use_stock' => $useStock, 'stock' => $stock,
        'images' => [],
    ]));
}

it('laadt de bestaande regels in het formulier', function () {
    $order = pageOrder();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->assertSet('data.lines.0.name', 'Oud product')
        ->assertSet('data.lines.0.quantity', 1);
});

it('maakt een vervangende order aan bij het opslaan van een betaalde order', function () {
    $order = pageOrder();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines', [
            ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 121.0, 'vat_rate' => 21],
        ])
        ->set('data.send_customer_email', false)
        ->call('submit');

    $order = $order->fresh();

    expect($order->replaced_by_order_id)->not->toBeNull()
        ->and(round($order->replacedByOrder->total, 2))->toBe(121.0);
});

it('past een concept in plaats aan bij het opslaan', function () {
    $order = pageOrder(invoiceId: 'PROFORMA', status: Order::STATUS_CONCEPT);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines', [
            ['order_product_id' => null, 'product_id' => null, 'name' => 'Nieuw product', 'quantity' => 1, 'price' => 60.5, 'vat_rate' => 21],
        ])
        ->set('data.send_customer_email', false)
        ->call('submit');

    $order = $order->fresh();

    expect($order->replaced_by_order_id)->toBeNull()
        ->and(round($order->total, 2))->toBe(60.5)
        ->and($order->orderProducts()->first()->name)->toBe('Nieuw product');
});

it('rekent de kwantiteit door in het regeltotaal, ongeacht de weergavevoorkeur van de beheerder', function () {
    // Deze beheerder ziet ex-BTW-prijzen. Product::getCurrentPriceAttribute()
    // zou daardoor een lagere waarde teruggeven dan de opgeslagen kolom; de
    // orderregel mag daar niet van afhangen.
    $admin = User::factory()->create(['role' => 'superadmin', 'show_prices_ex_vat' => true]);
    $this->actingAs($admin);

    $order = pageOrder(invoiceId: 'PROFORMA', status: Order::STATUS_CONCEPT);
    $product = modifyPageProduct(price: 50.0);

    $component = Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines.0.product_id', $product->id);

    // Bestaande regel had quantity 1: 50 (ruwe current_price) * 1 = 50.
    $component->assertSet('data.lines.0.price', 50.0);

    // Kwantiteit wijzigen na het kiezen van het product moet het regeltotaal
    // opnieuw berekenen, niet alleen het kiezen zelf.
    $component->set('data.lines.0.quantity', 3)
        ->assertSet('data.lines.0.price', 150.0);
});

it('behoudt product_extras van een ongewijzigde regel bij het opslaan', function () {
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => Order::STATUS_CONCEPT,
        'invoice_id' => 'PROFORMA',
        'total' => 100,
        'subtotal' => 100,
    ]);
    $extras = [['id' => 1, 'name' => 'Gravure', 'value' => 'Jan']];
    OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Product met extras',
        'quantity' => 1,
        'price' => 100,
        'vat_rate' => 21,
        'product_extras' => $extras,
    ]);
    $order = $order->fresh();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.send_customer_email', false)
        ->call('submit');

    expect($order->fresh()->orderProducts()->first()->product_extras)->toBe($extras);
});

it('boekt de voorraad van de nieuwe order niet af wanneer deduct_new_stock uitstaat', function () {
    $order = pageOrder();
    $product = modifyPageProduct(price: 10.0, useStock: true, stock: 5);

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->set('data.lines', [
            ['order_product_id' => null, 'product_id' => $product->id, 'name' => 'Voorraadproduct', 'quantity' => 3, 'price' => 30.0, 'vat_rate' => 21],
        ])
        ->set('data.send_customer_email', false)
        ->set('data.deduct_new_stock', false)
        ->call('submit');

    // De oude regel had geen product_id, dus niets om terug te boeken. De
    // nieuwe regel heeft deduct_new_stock uitstaan: voorraad blijft op 5.
    expect($product->fresh()->stock)->toBe(5);
});

it('weigert het scherm te tonen voor een order die niet gewijzigd mag worden en stuurt terug met een melding', function () {
    $order = pageOrder();
    $order->status = 'cancelled';
    $order->save();

    Livewire::test(ModifyOrder::class, ['record' => $order->id])
        ->assertRedirect(route('filament.dashed.resources.orders.view', ['record' => $order->id]));
});
