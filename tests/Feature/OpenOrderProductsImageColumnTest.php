<?php

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages\ListOpenOrderProducts;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables\OpenOrderProductsTable;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Customsetting::set('taxes_prices_include_taxes', 1);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
});

/**
 * MediaHelper::getSingleMedia() geeft een niet-numerieke string ongewijzigd
 * terug en behandelt hem als URL. Daarmee zijn deze tests niet afhankelijk van
 * een gevulde medialibrary.
 */
function openOrderProduct(?string $productImage, ?string $groupImage, int $quantity = 1): OrderProduct
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Groep', 'nl' => 'Groep'],
        'slug' => ['en' => 'groep-' . uniqid()],
        'short_description' => ['en' => ''], 'description' => ['en' => ''],
        'content' => ['en' => ''], 'search_terms' => ['en' => ''],
        'site_ids' => ['site'],
        'images' => $groupImage ? [$groupImage] : [],
    ]);

    $product = Product::withoutEvents(fn () => Product::create([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Testproduct'],
        'slug' => ['en' => 'test-' . uniqid()],
        'site_ids' => ['site'],
        'price' => 10, 'current_price' => 10, 'vat_rate' => 21,
        'images' => $productImage ? [$productImage] : [],
    ]));

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
        'invoice_id' => '2026-' . substr(uniqid(), -4),
        'total' => 10,
        'subtotal' => 10,
    ]);

    return OrderProduct::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'name' => 'Testproduct',
        'quantity' => $quantity,
        'price' => 10,
        'vat_rate' => 21,
    ]);
}

it('toont de productfoto op het tabblad per orderregel', function () {
    $line = openOrderProduct(productImage: 'https://example.test/product.jpg', groupImage: null);

    Livewire::test(ListOpenOrderProducts::class)
        ->assertTableColumnExists('image')
        ->assertTableColumnStateSet('image', 'https://example.test/product.jpg', $line);
});

it('valt terug op de foto van de productgroep als het product er zelf geen heeft', function () {
    $line = openOrderProduct(productImage: null, groupImage: 'https://example.test/groep.jpg');

    Livewire::test(ListOpenOrderProducts::class)
        ->assertTableColumnStateSet('image', 'https://example.test/groep.jpg', $line);
});

it('laat de kolom leeg bij een regel zonder gekoppeld product', function () {
    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'paid',
        'fulfillment_status' => 'unhandled',
        'invoice_id' => '2026-9999',
        'total' => 10,
        'subtotal' => 10,
    ]);
    $line = OrderProduct::create([
        'order_id' => $order->id,
        'name' => 'Losse kassaregel',
        'quantity' => 1,
        'price' => 10,
        'vat_rate' => 21,
    ]);

    Livewire::test(ListOpenOrderProducts::class)
        ->assertTableColumnStateSet('image', null, $line);
});

/**
 * Op dit tabblad komen de rijen uit een fromSub-query, en dan lost
 * getTableRecord() de sleutel niet meer op. Daarom de kolom rechtstreeks op de
 * rij uit de tabel zetten: zo lopen de echte gegroepeerde query en de echte
 * kolom allebei mee.
 */
it('toont de productfoto op het tabblad gegroepeerd per product', function () {
    openOrderProduct(productImage: 'https://example.test/product.jpg', groupImage: null);

    $component = Livewire::test(ListOpenOrderProducts::class)->set('activeTab', 'grouped');
    $record = $component->instance()->getTableRecords()->first();

    expect($record)->not->toBeNull();

    $column = $component->instance()->getTable()->getColumn('image');
    $column->record($record);
    $column->clearCachedState();

    expect($column->getState())->toBe('https://example.test/product.jpg');
});

/**
 * De kern van dit testbestand, en de reden dat de resolutie in een eigen
 * methode zit. Op het tabblad gegroepeerd per productgroep staat in de kolom
 * product_id de product_group_id, een alias uit de subquery. Wie daar
 * $record->product leest krijgt het product dat toevallig dat id draagt, en dus
 * de foto van een heel ander artikel.
 *
 * Die tab draait via de tabel niet te toetsen: de subquery gebruikt
 * JSON_UNQUOTE/JSON_EXTRACT en de suite draait op SQLite. Daarom rechtstreeks
 * op de methode, met een rij die precies de vorm heeft die de subquery oplevert.
 */
it('toont de foto van de productgroep op het tabblad gegroepeerd per productgroep', function () {
    $line = openOrderProduct(productImage: 'https://example.test/product.jpg', groupImage: 'https://example.test/groep.jpg');

    $groupRow = new OrderProduct(['name' => 'Groep', 'quantity' => 1]);
    $groupRow->product_id = $line->product->product_group_id;

    expect(OpenOrderProductsTable::imageUrlFor($groupRow, 'grouped_product_group'))
        ->toBe('https://example.test/groep.jpg');
});

it('pakt op het productgroep-tabblad niet de foto van het product met dat id', function () {
    $line = openOrderProduct(productImage: 'https://example.test/product.jpg', groupImage: 'https://example.test/groep.jpg');
    $groupId = $line->product->product_group_id;

    // Een product waarvan het id gelijk is aan de productgroep-id zou bij een
    // naieve $record->product-lookup opduiken. Deze toets legt vast dat dat
    // niet gebeurt.
    $verkeerd = Product::find($groupId);
    expect($verkeerd)->not->toBeNull();
    $verkeerd->images = ['https://example.test/verkeerd.jpg'];
    $verkeerd->saveQuietly();

    $groupRow = new OrderProduct(['name' => 'Groep', 'quantity' => 1]);
    $groupRow->product_id = $groupId;

    expect(OpenOrderProductsTable::imageUrlFor($groupRow, 'grouped_product_group'))
        ->toBe('https://example.test/groep.jpg');
});
