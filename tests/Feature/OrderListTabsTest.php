<?php

use Livewire\Livewire;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedEcommerceCore\Classes\OrderListTabs;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ListOrders;
use Dashed\DashedEcommerceCore\Filament\Widgets\Orders\OrderWorkflowStatsWidget;

uses(RefreshDatabase::class);

beforeEach(fn () => alsBeheerder());

/**
 * One order per tab, plus the orders that must stay out of every work tab.
 *
 * @return array<string, Order>
 */
function ordersPerTab(): array
{
    $orders = [
        'nieuw' => Order::factory()->create(['fulfillment_status' => 'unhandled']),
        'inpakken' => Order::factory()->create(['fulfillment_status' => 'in_treatment']),
        'verzenden' => Order::factory()->create(['fulfillment_status' => 'packed']),
        'ophalen' => Order::factory()->create(['fulfillment_status' => 'ready_for_pickup']),
        'afgerond' => Order::factory()->create(['fulfillment_status' => 'shipped']),
        'afgehandeld' => Order::factory()->create(['fulfillment_status' => 'handled']),
        'concept' => Order::factory()->concept()->create(['fulfillment_status' => 'unhandled']),
        'credit' => Order::factory()->create(['status' => 'return', 'fulfillment_status' => 'unhandled']),
        'openRetour' => Order::factory()->create(['fulfillment_status' => 'handled']),
        'geslotenRetour' => Order::factory()->create(['fulfillment_status' => 'handled']),
    ];

    $open = new OrderReturn();
    $open->forceFill(['order_id' => $orders['openRetour']->id, 'status' => OrderReturn::STATUS_APPROVED, 'email' => 'retour@example.com'])->save();

    $closed = new OrderReturn();
    $closed->forceFill(['order_id' => $orders['geslotenRetour']->id, 'status' => OrderReturn::STATUS_CLOSED, 'email' => 'retour@example.com'])->save();

    return $orders;
}

it('shows exactly the right orders per tab', function () {
    $o = ordersPerTab();

    $tab = fn (string $name) => Livewire::test(ListOrders::class)->set('activeTab', $name);

    $tab('nieuw')
        ->assertCanSeeTableRecords([$o['nieuw']])
        ->assertCanNotSeeTableRecords([$o['inpakken'], $o['concept'], $o['credit']])
        ->assertCountTableRecords(1);

    $tab('inpakken')->assertCanSeeTableRecords([$o['inpakken']])->assertCountTableRecords(1);

    $tab('verzenden')->assertCanSeeTableRecords([$o['verzenden'], $o['ophalen']])->assertCountTableRecords(2);

    $tab('afgerond')
        ->assertCanSeeTableRecords([$o['afgerond'], $o['afgehandeld'], $o['openRetour'], $o['geslotenRetour']])
        ->assertCountTableRecords(4);

    // The default status filter (paid statuses) is ignored here, otherwise the
    // credit order would never show.
    $tab('retouren')
        ->assertCanSeeTableRecords([$o['credit'], $o['openRetour']])
        ->assertCanNotSeeTableRecords([$o['geslotenRetour'], $o['nieuw']])
        ->assertCountTableRecords(2);

    // Alle follows the default status filter, like the list always did.
    $tab('alle')
        ->assertCanNotSeeTableRecords([$o['concept'], $o['credit']])
        ->assertCountTableRecords(8);
});

it('counts every tab from the same filtered query as the list', function () {
    ordersPerTab();

    $page = Livewire::test(ListOrders::class);

    expect($page->instance()->getTabCounts())->toBe([
        'alle' => 8,
        'nieuw' => 1,
        'inpakken' => 1,
        'verzenden' => 2,
        'afgerond' => 4,
        'retouren' => 2,
    ]);

    // Clearing the status filter widens Alle, not the work tabs (they carry
    // their own paid-status condition).
    $page->filterTable('status', []);
    expect($page->instance()->getTabCounts()['alle'])->toBe(10)
        ->and($page->instance()->getTabCounts()['nieuw'])->toBe(1);

    // Each counter equals the rows its tab shows.
    foreach (array_keys(OrderListTabs::labels()) as $name) {
        $tab = Livewire::test(ListOrders::class)->set('activeTab', $name);
        $tab->assertCountTableRecords($tab->instance()->getTabCounts()[$name]);
    }
});

it('renders the design columns and keeps the rest toggleable', function () {
    $order = Order::factory()->create(['is_priority' => true]);

    Livewire::test(ListOrders::class)
        ->assertCanRenderTableColumn('is_priority')
        ->assertCanRenderTableColumn('invoice_id')
        ->assertCanRenderTableColumn('payment_method')
        ->assertCanRenderTableColumn('payment_status')
        ->assertCanRenderTableColumn('fulfillment_status')
        ->assertCanRenderTableColumn('parcel_type')
        ->assertCanRenderTableColumn('deadline')
        ->assertCanRenderTableColumn('name')
        ->assertCanRenderTableColumn('total')
        ->assertCanRenderTableColumn('created_at')
        ->assertTableColumnExists('orderProducts.name')
        ->assertTableColumnExists('trackAndTraces.code')
        ->assertTableColumnExists('utm_source')
        ->assertCanSeeTableRecords([$order]);
});

it('keeps the number of list queries flat as the page fills up', function () {
    Order::factory()->metProducten(1)->count(3)->create();

    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(ListOrders::class)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    $small = $count();

    Order::factory()->metProducten(2)->count(20)->create();

    expect($count())->toBe($small);
});

it('renders the work-queue widget with one aggregate query', function () {
    ordersPerTab();

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(OrderWorkflowStatsWidget::class)
        ->assertOk()
        ->assertSee('Te verwerken')
        ->assertSee('Inpakken')
        ->assertSee('Verzenden');
    $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'dashed__orders'));
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1);
});

it('keeps a status the admin chose on the retouren tab', function () {
    $o = ordersPerTab();

    // Default status filter: skipped, credit order and open return both show.
    Livewire::test(ListOrders::class)
        ->set('activeTab', 'retouren')
        ->assertCanSeeTableRecords([$o['credit'], $o['openRetour']])
        ->assertCountTableRecords(2);

    // A status chosen by the admin applies on this tab too, and in its counter.
    $page = Livewire::test(ListOrders::class)
        ->filterTable('status', ['return'])
        ->set('activeTab', 'retouren')
        ->assertCanSeeTableRecords([$o['credit']])
        ->assertCanNotSeeTableRecords([$o['openRetour']])
        ->assertCountTableRecords(1);

    expect($page->instance()->getTabCounts()['retouren'])->toBe(1);
});
