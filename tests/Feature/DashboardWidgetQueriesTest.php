<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\RevenueStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Dashboard\CartStatistics;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\AlltimeRevenueStats;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\PaymentMethodPieChartWidget;
use Dashed\DashedEcommerceCore\Filament\Widgets\Revenue\MonthlyRevenueAndReturnLineChartStats;

function makeDashboardOrder(string $createdAt, float $total, string $status = 'paid', array $lines = []): Order
{
    $order = Order::withoutEvents(fn () => Order::create([
        'total' => $total,
        'status' => $status,
        'email' => 'klant@example.com',
        'site_id' => 'site',
        'ip' => '127.0.0.1',
        'hash' => bin2hex(random_bytes(8)),
    ]));
    $order->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();

    foreach ($lines as [$sku, $quantity]) {
        OrderProduct::create([
            'order_id' => $order->id,
            'name' => $sku,
            'sku' => $sku,
            'quantity' => $quantity,
            'price' => 1,
            'vat_rate' => 21,
        ]);
    }

    return $order;
}

function dashboardFilters(string $start, string $end, string $steps): array
{
    return ['startDate' => $start, 'endDate' => $end, 'period' => 'custom', 'steps' => $steps];
}

/** Roept de beschermde reken-methode van een widget aan en telt de queries. */
function runWidget(object $widget, string $method): array
{
    DB::connection()->enableQueryLog();
    DB::connection()->flushQueryLog();

    $result = (new ReflectionMethod($widget, $method))->invoke($widget);

    // Alleen de queries op de gegevens zelf tellen mee; het valutaformaat leest
    // er een instelling bij en dat is niet wat deze tests bewaken.
    $queries = collect(DB::connection()->getQueryLog())
        ->reject(fn ($q) => str_contains($q['query'], 'custom_settings') || str_contains($q['query'], 'information_schema') || str_contains($q['query'], 'sqlite_master'))
        ->values()
        ->all();
    DB::connection()->disableQueryLog();

    return [$result, $queries];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');
    Cache::flush();
});

it('rekent de omzetgrafiek uit zonder een query per stap', function () {
    makeDashboardOrder('2026-03-02 10:00:00', 100);
    makeDashboardOrder('2026-03-02 15:00:00', 50);
    makeDashboardOrder('2026-03-04 09:00:00', 25);
    makeDashboardOrder('2026-03-04 11:00:00', 10, 'return');
    makeDashboardOrder('2026-03-03 11:00:00', 999, 'pending');

    $widget = new MonthlyRevenueAndReturnLineChartStats();
    $widget->filters = dashboardFilters('01-03-2026', '05-03-2026', 'per_day');
    [$data, $perDag] = runWidget($widget, 'getData');

    $verkopen = array_combine($data['labels'], $data['datasets'][0]['data']);
    $retouren = array_combine($data['labels'], $data['datasets'][1]['data']);

    expect($verkopen['02-03-2026'])->toBe('150.00')
        ->and($verkopen['03-03-2026'])->toBe('0.00')
        ->and($verkopen['04-03-2026'])->toBe('25.00')
        ->and($retouren['04-03-2026'])->toBe('10.00');

    // Per uur over dezelfde vijf dagen zijn er 24 keer zoveel stappen; het
    // aantal queries hoort daar niets van te merken.
    $widget->filters = dashboardFilters('01-03-2026', '05-03-2026', 'per_hour');
    [, $perUur] = runWidget($widget, 'getData');

    expect(count($perUur))->toBe(count($perDag))
        ->and(count($perUur))->toBeLessThanOrEqual(3);
});

it('telt de omzet van de periode in de database in plaats van alle orders te laden', function () {
    makeDashboardOrder('2026-03-02 10:00:00', 100, 'paid', [['A', 2], ['shipping_costs', 1]]);
    makeDashboardOrder('2026-03-03 10:00:00', 50, 'paid', [['B', 3]]);
    makeDashboardOrder('2026-03-04 10:00:00', 30, 'return', [['A', 1]]);
    makeDashboardOrder('2026-04-04 10:00:00', 999, 'paid', [['A', 9]]);

    $widget = new RevenueStats();
    $widget->filters = dashboardFilters('01-03-2026', '05-03-2026', 'per_day');
    [$cards, $queries] = runWidget($widget, 'getCards');

    expect($cards[0]->getValue())->toBe(2)
        ->and($cards[0]->getDescription())->toBe('1 retour')
        ->and($cards[1]->getValue())->toBe(CurrencyHelper::formatPrice(150))
        ->and($cards[2]->getValue())->toBe(CurrencyHelper::formatPrice(75))
        ->and($cards[3]->getValue())->toBe(5)
        ->and($cards[3]->getDescription())->toBe('1 retour')
        ->and(count($queries))->toBeLessThanOrEqual(4)
        ->and(collect($queries)->pluck('query')->filter(fn ($q) => preg_match('/order_id\W* in \(\?/', $q)))->toBeEmpty();
});

it('telt de totale omzet in de database in plaats van alle orders te laden', function () {
    makeDashboardOrder('2025-03-02 10:00:00', 100, 'paid', [['A', 2]]);
    makeDashboardOrder('2026-03-03 10:00:00', 50, 'paid', [['B', 3]]);
    makeDashboardOrder('2026-03-04 10:00:00', 30, 'return', [['A', 1]]);

    [$cards, $queries] = runWidget(new AlltimeRevenueStats(), 'getCards');

    expect($cards[0]->getValue())->toBe(2)
        ->and($cards[1]->getValue())->toBe(CurrencyHelper::formatPrice(150))
        ->and($cards[3]->getValue())->toBe(5)
        ->and($cards[3]->getDescription())->toBe('1 retour')
        ->and(count($queries))->toBeLessThanOrEqual(4)
        ->and(collect($queries)->pluck('query')->filter(fn ($q) => preg_match('/order_id\W* in \(\?/', $q)))->toBeEmpty();
});

it('telt de winkelwagens in de database in plaats van alle regels te laden', function () {
    $vol = Cart::create(['token' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'default']);
    $ookVol = Cart::create(['token' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'default']);
    Cart::create(['token' => (string) \Illuminate\Support\Str::uuid(), 'type' => 'default']);
    CartItem::create(['cart_id' => $vol->id, 'quantity' => 2, 'unit_price' => 10, 'options_hash' => str_repeat('a', 64)]);
    CartItem::create(['cart_id' => $vol->id, 'quantity' => 1, 'unit_price' => 5, 'options_hash' => str_repeat('b', 64)]);
    CartItem::create(['cart_id' => $ookVol->id, 'quantity' => 3, 'unit_price' => 1, 'options_hash' => str_repeat('c', 64)]);

    [$cards, $queries] = runWidget(new CartStatistics(), 'getCards');

    expect($cards[0]->getValue())->toBe(2)
        ->and($cards[1]->getValue())->toBe(6)
        ->and($cards[2]->getValue())->toBe(CurrencyHelper::formatPrice(28))
        ->and(count($queries))->toBeLessThanOrEqual(2);
});

it('groepeert de betaalmethodes in de database en schrijft niets weg', function () {
    $order = makeDashboardOrder('2026-03-02 10:00:00', 100);
    foreach ([['ideal', '2026-03-02 10:00:00'], ['ideal', '2026-03-03 10:00:00'], ['creditcard', '2026-03-03 12:00:00'], ['ideal', '2026-04-03 12:00:00']] as [$method, $at]) {
        $payment = OrderPayment::create(['order_id' => $order->id, 'status' => 'paid', 'amount' => 10, 'payment_method' => $method, 'psp' => 'mollie']);
        $payment->forceFill(['created_at' => Carbon::parse($at)])->saveQuietly();
    }
    $onbetaald = OrderPayment::create(['order_id' => $order->id, 'status' => 'pending', 'amount' => 10, 'payment_method' => 'ideal', 'psp' => 'mollie']);
    $onbetaald->forceFill(['created_at' => Carbon::parse('2026-03-02 10:00:00')])->saveQuietly();

    $widget = new PaymentMethodPieChartWidget();
    $widget->filters = dashboardFilters('01-03-2026', '05-03-2026', 'per_day');
    [$data, $queries] = runWidget($widget, 'getData');

    $perMethode = array_combine($data['labels'], $data['datasets'][0]['data']);

    expect($perMethode)->toBe(['creditcard' => 1, 'ideal' => 2])
        ->and(count($queries))->toBeLessThanOrEqual(2)
        ->and(collect($queries)->pluck('query')->filter(fn ($q) => str_starts_with($q, 'update')))->toBeEmpty();
});
