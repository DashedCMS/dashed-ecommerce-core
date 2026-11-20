<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedCore\Dashboard\DashboardPriorities;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\Dashboard\EcommercePriorities;
use Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\KpiRowWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\RevenueTrendChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\RecentOrdersWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\SalesChannelsWidget;
use Dashed\DashedEcommerceCore\DashedEcommerceCoreServiceProvider;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Pages\ListOrders;

function auroraOrder(string $createdAt, float $total, string $status = 'paid', array $extra = []): Order
{
    $order = Order::withoutEvents(fn () => Order::create(array_merge([
        'total' => $total,
        'status' => $status,
        'email' => 'klant@example.com',
        'site_id' => 'site',
        'ip' => '127.0.0.1',
        'hash' => bin2hex(random_bytes(8)),
    ], $extra)));
    $order->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();

    return $order;
}

function auroraFilters(string $start, string $end, string $steps = 'per_day'): array
{
    return ['startDate' => $start, 'endDate' => $end, 'period' => 'custom', 'steps' => $steps];
}

/** Roept een methode van een widget aan en telt de queries op de gegevens zelf. */
function auroraRun(object $widget, string $method): array
{
    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $result = (new ReflectionMethod($widget, $method))->invoke($widget);

    $queries = collect(DB::connection()->getQueryLog())
        ->reject(fn ($q) => str_contains($q['query'], 'custom_settings') || str_contains($q['query'], 'information_schema') || str_contains($q['query'], 'sqlite_master'))
        ->values()
        ->all();
    DB::connection()->disableQueryLog();

    return [$result, $queries];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Cache::flush();
});

it('rekent de KPI-rij met delta tegen de vorige periode in twee queries uit', function () {
    // Huidige periode 11 t/m 20 maart, vorige periode 1 t/m 10 maart.
    auroraOrder('2026-03-12 10:00:00', 100);
    auroraOrder('2026-03-15 10:00:00', 50);
    auroraOrder('2026-03-18 10:00:00', 999, 'pending');
    auroraOrder('2026-03-05 10:00:00', 100);
    auroraOrder('2026-02-20 10:00:00', 777);
    auroraOrder('2026-03-01 10:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled']);

    $widget = new KpiRowWidget();
    $widget->filters = auroraFilters('11-03-2026', '20-03-2026');
    [$kpis, $queries] = auroraRun($widget, 'getKpis');

    expect($kpis[0]['value'])->toBe(CurrencyHelper::formatPrice(150))
        ->and($kpis[0]['delta'])->toBe('+25,0%')
        ->and($kpis[0]['deltaPositive'])->toBeTrue()
        ->and($kpis[1]['value'])->toBe('2')
        ->and($kpis[1]['delta'])->toBe('+0,0%')
        ->and($kpis[2]['value'])->toBe(CurrencyHelper::formatPrice(75))
        // Vorige periode: 100 + 20 over twee orders, gemiddeld 60.
        ->and($kpis[2]['delta'])->toBe('+25,0%')
        ->and($kpis[2]['deltaPositive'])->toBeTrue()
        // Alle vijf betaalde orders staan nog op unhandled (de standaard), los van de periode.
        ->and($kpis[3]['value'])->toBe('5')
        ->and($kpis[3]['url'])->toContain('fulfillment_status')
        ->and(count($queries))->toBeLessThanOrEqual(2);
});

it('toont geen delta als de vorige periode leeg is', function () {
    auroraOrder('2026-03-12 10:00:00', 100);

    $widget = new KpiRowWidget();
    $widget->filters = auroraFilters('11-03-2026', '20-03-2026');
    [$kpis] = auroraRun($widget, 'getKpis');

    expect($kpis[0]['delta'])->toBeNull()
        ->and($kpis[0]['deltaPositive'])->toBeNull();
});

it('wisselt de omzetgrafiek tussen omzet en bestellingen zonder query per stap', function () {
    auroraOrder('2026-03-02 10:00:00', 100);
    auroraOrder('2026-03-02 15:00:00', 50);
    auroraOrder('2026-03-04 09:00:00', 25);
    auroraOrder('2026-03-04 11:00:00', 10, 'return');

    $widget = new RevenueTrendChart();
    $widget->filters = auroraFilters('01-03-2026', '05-03-2026');
    [$omzet, $perDag] = auroraRun($widget, 'getData');

    expect($omzet['datasets'][0]['data'])->toBe([0.0, 150.0, 0.0, 25.0, 0.0])
        ->and($omzet['labels'])->toHaveCount(5);

    $widget->filter = 'orders';
    [$bestellingen] = auroraRun($widget, 'getData');
    expect($bestellingen['datasets'][0]['data'])->toBe([0, 2, 0, 1, 0]);

    $widget->filters = auroraFilters('01-03-2026', '05-03-2026', 'per_hour');
    [, $perUur] = auroraRun($widget, 'getData');

    expect(count($perUur))->toBe(count($perDag))
        ->and(count($perUur))->toBeLessThanOrEqual(2);
});

it('verdeelt de omzet per verkoopkanaal met één GROUP BY', function () {
    auroraOrder('2026-03-02 10:00:00', 60, 'paid', ['order_origin' => 'own']);
    auroraOrder('2026-03-02 11:00:00', 15, 'paid', ['order_origin' => '']);
    auroraOrder('2026-03-03 10:00:00', 25, 'paid', ['order_origin' => 'Bol']);
    auroraOrder('2026-03-03 12:00:00', 500, 'pending', ['order_origin' => 'Bol']);
    auroraOrder('2026-04-03 12:00:00', 500, 'paid', ['order_origin' => 'Bol']);

    $widget = new SalesChannelsWidget();
    $widget->filters = auroraFilters('01-03-2026', '05-03-2026');
    [$channels, $queries] = auroraRun($widget, 'getChannels');

    expect($channels['total'])->toBe(100.0)
        ->and($channels['rows'])->toHaveCount(2)
        ->and($channels['rows'][0]['label'])->toBe('Eigen webshop')
        ->and($channels['rows'][0]['percentageFormatted'])->toBe('75%')
        ->and($channels['rows'][1]['label'])->toBe('Bol.com')
        ->and($channels['rows'][1]['offset'])->toBe(75.0)
        ->and(count($queries))->toBe(1);
});

it('bundelt kanalen vanaf het vijfde onder Overig', function () {
    foreach (['a' => 50, 'b' => 40, 'c' => 30, 'd' => 20, 'e' => 10, 'f' => 5] as $origin => $total) {
        auroraOrder('2026-03-02 10:00:00', $total, 'paid', ['order_origin' => $origin]);
    }

    $widget = new SalesChannelsWidget();
    $widget->filters = auroraFilters('01-03-2026', '05-03-2026');
    [$channels] = auroraRun($widget, 'getChannels');

    expect($channels['rows'])->toHaveCount(5)
        ->and($channels['rows'][4]['label'])->toBe('Overig')
        ->and($channels['rows'][4]['amount'])->toBe(15.0);
});

it('toont de vijf recentste betaalde of lopende bestellingen in één query', function () {
    auroraOrder('2026-03-01 10:00:00', 10, 'paid', ['first_name' => 'Oud', 'last_name' => 'Klant']);
    foreach (range(2, 6) as $day) {
        auroraOrder("2026-03-0{$day} 10:00:00", $day, $day === 6 ? 'pending' : 'paid', ['first_name' => 'Klant', 'last_name' => (string) $day]);
    }
    auroraOrder('2026-03-09 10:00:00', 99, 'cancelled');

    [$orders, $queries] = auroraRun(new RecentOrdersWidget(), 'getOrders');

    expect($orders)->toHaveCount(5)
        ->and($orders[0]['customer'])->toBe('Klant 6')
        ->and($orders[0]['tone'])->toBe('blue')
        ->and($orders[1]['tone'])->toBe('mint')
        ->and(collect($orders)->pluck('customer'))->not->toContain('Oud Klant')
        ->and(count($queries))->toBe(1);
});

it('sorteert, begrenst en normaliseert het prioriteitenregister', function () {
    cms()->clearBuilder('dashboardPriorities');
    cms()->builder('dashboardPriorities', [
        'laag' => fn () => [['title' => 'Laag', 'url' => '/laag', 'sort' => 90, 'tone' => 'paars']],
        'kapot' => fn () => throw new RuntimeException('bron stuk'),
        'hoog' => fn () => [
            ['title' => 'Hoog', 'url' => '/hoog', 'sort' => 5, 'badge' => '3', 'tone' => 'red'],
            ['title' => 'Zonder url'],
        ],
    ]);

    $all = DashboardPriorities::all();

    expect(collect($all)->pluck('title')->all())->toBe(['Hoog', 'Laag'])
        ->and($all[1]['tone'])->toBe('neutral')
        ->and($all[0]['badge'])->toBe('3')
        ->and(DashboardPriorities::count())->toBe(2)
        ->and(DashboardPriorities::top(1))->toHaveCount(1);
});

it('meldt de ec-core prioriteiten met een telling per bron', function () {
    cms()->clearBuilder('dashboardPriorities');
    cms()->builder('dashboardPriorities', EcommercePriorities::sources());

    auroraOrder('2026-03-01 10:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled', 'is_priority' => true]);
    auroraOrder('2026-03-01 11:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled']);
    auroraOrder('2026-03-01 12:00:00', 20, 'paid', ['fulfillment_status' => 'handled', 'is_priority' => true]);
    auroraOrder('2026-03-01 13:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled', 'ship_by_date' => now()->subDay()->toDateString()]);
    $order = auroraOrder('2026-03-01 14:00:00', 20);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_REQUESTED]);
    OrderReturn::create(['order_id' => $order->id, 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_HANDLED]);
    Quote::create(['title' => 'Offerte', 'email' => 'klant@example.test', 'valid_until' => now()->addDays(14), 'status' => Quote::STATUS_SENT]);

    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();
    $items = collect(DashboardPriorities::all())->keyBy('key');
    $queries = collect(DB::connection()->getQueryLog())
        ->reject(fn ($q) => str_contains($q['query'], 'custom_settings') || str_contains($q['query'], 'permission') || str_contains($q['query'], 'role'))
        ->count();
    DB::connection()->disableQueryLog();

    expect($items->keys()->all())->toBe(['ec-deadline.0', 'ec-priority-orders.0', 'ec-unhandled-orders.0', 'ec-return-requests.0', 'ec-quotes-waiting.0'])
        ->and($items['ec-unhandled-orders.0']['badge'])->toBe('4')
        ->and($items['ec-unhandled-orders.0']['url'])->toContain('tab=nieuw')
        ->and($items['ec-priority-orders.0']['title'])->toBe('1 prioriteitsbestelling')
        ->and($items['ec-priority-orders.0']['url'])->toContain('is_priority')
        ->and($items['ec-return-requests.0']['url'])->toContain('requested')
        ->and($items['ec-quotes-waiting.0']['url'])->toContain('sent')
        ->and($queries)->toBeLessThanOrEqual(6);
});

it('laat prioriteiten weg voor een gebruiker zonder rechten', function () {
    cms()->clearBuilder('dashboardPriorities');
    cms()->builder('dashboardPriorities', EcommercePriorities::sources());
    auroraOrder('2026-03-01 11:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled']);

    $this->actingAs(User::factory()->create(['role' => 'customer']));

    expect(DashboardPriorities::all())->toBe([]);
});

it('zet de Aurora-widgets bovenaan de standaardindeling, boven de bestaande', function () {
    $ids = array_keys(app(\Dashed\DashedCore\Dashboard\DashboardWidgetRegistry::class)->all());

    $aurora = ['aurora-workday', 'ec-aurora-kpis', 'ec-aurora-revenue-trend', 'aurora-priorities', 'ec-aurora-sales-channels', 'ec-aurora-recent-orders'];

    expect(array_slice($ids, 0, count($aurora)))->toBe($aurora)
        ->and($ids)->toContain('ec-revenue', 'welcome');
});

/** Mount de orderlijst met de query-string van een prioriteitslink. */
function auroraListFromUrl(string $url)
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

    return Livewire\Livewire::withQueryParams($params)->test(ListOrders::class);
}

it('toont achter de prioriteitslink precies de getelde bestellingen', function () {
    $open = [
        auroraOrder('2026-03-01 10:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled', 'is_priority' => true]),
        auroraOrder('2026-03-01 10:05:00', 20, 'waiting_for_confirmation', ['fulfillment_status' => 'in_treatment', 'is_priority' => true]),
    ];
    $hidden = [
        auroraOrder('2026-03-01 11:00:00', 20, 'paid', ['fulfillment_status' => 'packed', 'is_priority' => true]),
        auroraOrder('2026-03-01 12:00:00', 20, 'paid', ['fulfillment_status' => 'shipped', 'is_priority' => true]),
        auroraOrder('2026-03-01 13:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled']),
        auroraOrder('2026-03-01 14:00:00', 20, 'pending', ['fulfillment_status' => 'unhandled', 'is_priority' => true]),
    ];

    $item = EcommercePriorities::priorityOrders()[0];

    expect($item['title'])->toBe('2 prioriteitsbestellingen');

    auroraListFromUrl($item['url'])
        ->assertCanSeeTableRecords($open)
        ->assertCanNotSeeTableRecords($hidden)
        ->assertCountTableRecords(2);
});

it('telt betaald-niet-afgehandeld als de tab Nieuw en linkt daarheen', function () {
    $nieuw = [
        auroraOrder('2026-03-01 10:00:00', 20, 'paid', ['fulfillment_status' => 'unhandled']),
        auroraOrder('2026-03-01 10:05:00', 20, 'partially_paid', ['fulfillment_status' => 'unhandled']),
        auroraOrder('2026-03-01 10:10:00', 20, 'waiting_for_confirmation', ['fulfillment_status' => 'unhandled']),
    ];
    $hidden = [
        auroraOrder('2026-03-01 11:00:00', 20, 'pending', ['fulfillment_status' => 'unhandled']),
        auroraOrder('2026-03-01 12:00:00', 20, 'paid', ['fulfillment_status' => 'in_treatment']),
    ];

    $item = EcommercePriorities::unhandledOrders()[0];

    expect($item['badge'])->toBe('3')
        ->and($item['url'])->toContain('tab=nieuw');

    $list = auroraListFromUrl($item['url'])
        ->assertCanSeeTableRecords($nieuw)
        ->assertCanNotSeeTableRecords($hidden)
        ->assertCountTableRecords(3);

    expect($list->instance()->getTabCounts()['nieuw'])->toBe(3);
});

it('registreert de Aurora-widgets alleen in de Aurora-indeling', function () {
    config(['dashed-core.dashed_cms.layout' => 'classic']);
    expect(DashedEcommerceCoreServiceProvider::auroraDashboardWidgets())->toBe([]);

    config(['dashed-core.dashed_cms.layout' => 'dashed']);
    expect(array_keys(DashedEcommerceCoreServiceProvider::auroraDashboardWidgets()))
        ->toBe(['ec-aurora-kpis', 'ec-aurora-revenue-trend', 'ec-aurora-sales-channels', 'ec-aurora-recent-orders']);
});
