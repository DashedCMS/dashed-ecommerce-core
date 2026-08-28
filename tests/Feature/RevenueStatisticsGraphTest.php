<?php

use App\Models\User;
use Livewire\Livewire;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Filament\Pages\Statistics\RevenueStatisticsPage;

function makeGraphOrder(string $createdAt, float $total): Order
{
    $order = Order::withoutEvents(fn () => Order::create([
        'total' => $total,
        'status' => 'paid',
        'email' => 'klant@example.com',
        'site_id' => 'site',
        'ip' => '127.0.0.1',
        'hash' => bin2hex(random_bytes(8)),
    ]));

    $order->forceFill(['created_at' => Carbon::parse($createdAt)])->saveQuietly();

    return $order;
}

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');
});

it('verdeelt de omzet over de stappen van de grafiek', function () {
    makeGraphOrder('2026-03-02 10:00:00', 100);
    makeGraphOrder('2026-03-02 15:00:00', 50);
    makeGraphOrder('2026-03-04 09:00:00', 25);

    $graphData = Livewire::test(RevenueStatisticsPage::class)
        ->set('data.startDate', '2026-03-01')
        ->set('data.endDate', '2026-03-05')
        ->set('data.steps', 'per_day')
        ->get('graphData');

    $labels = $graphData['graph']['labels'];
    $values = $graphData['graph']['datasets'][0]['data'];
    $perDag = array_combine($labels, $values);

    expect($perDag['01-03-2026'])->toBe(0.0)
        ->and($perDag['02-03-2026'])->toBe(150.0)
        ->and($perDag['03-03-2026'])->toBe(0.0)
        ->and($perDag['04-03-2026'])->toBe(25.0);
});

it('vraagt de grafiek niet per stap op', function () {
    makeGraphOrder('2026-03-02 10:00:00', 100);

    $tellen = function (string $start, string $eind): int {
        $component = Livewire::test(RevenueStatisticsPage::class)
            ->set('data.steps', 'per_day')
            ->set('data.startDate', $start);

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();

        $component->set('data.endDate', $eind);

        $aantal = count(DB::connection()->getQueryLog());
        DB::connection()->disableQueryLog();

        return $aantal;
    };

    $eenDag = $tellen('2026-03-02', '2026-03-02');
    $eenJaar = $tellen('2026-01-01', '2026-12-31');

    // Het aantal queries hoort bij de filters, niet bij de lengte van de lijn.
    // Met een som per stap waren dit er een voor elk punt: een dag tegen ruim
    // driehonderd, en dat is waar de pagina op stukliep.
    expect($eenJaar)->toBe($eenDag);
});

it('geeft elk soort stap een eigen leesbaar label', function () {
    $component = Livewire::test(RevenueStatisticsPage::class)
        ->set('data.startDate', '2026-03-02')
        ->set('data.endDate', '2026-03-02')
        ->set('data.steps', 'per_hour');

    // Alles stond op d-m-Y, dus per uur gaf dat vierentwintig keer dezelfde
    // tekst onder de grafiek.
    $labels = $component->get('graphData')['graph']['labels'];

    expect($labels[0])->toBe('02-03 00:00')
        ->and($labels[1])->toBe('02-03 01:00')
        ->and(count(array_unique($labels)))->toBe(count($labels));
});

it('kapt een grafiek af die te veel punten zou krijgen', function () {
    $labels = Livewire::test(RevenueStatisticsPage::class)
        ->set('data.startDate', '2020-01-01')
        ->set('data.endDate', '2026-01-01')
        ->set('data.steps', 'per_hour')
        ->get('graphData')['graph']['labels'];

    // Zes jaar per uur is ruim vijftigduizend punten: onleesbaar, en genoeg om
    // de browser op te laten geven.
    expect(count($labels))->toBe(750);
});

it('laat een periode van een enkele dag toe', function () {
    makeGraphOrder('2026-03-02 10:00:00', 100);

    $component = Livewire::test(RevenueStatisticsPage::class)
        ->set('data.startDate', '2026-03-02')
        ->set('data.endDate', '2026-03-02')
        ->set('data.steps', 'per_day');

    // De einddatum moest eerder strikt na de startdatum liggen. Een dag
    // bekijken viel daardoor op de validatie stuk, en omdat de berekening dan
    // niet meer draaide bleef de grafiek zwijgend op de vorige periode staan.
    $component->assertHasNoErrors();

    $graphData = $component->get('graphData');

    expect($graphData['filters']['beginDate'])->toBe('2026-03-02 00:00:00')
        ->and($graphData['filters']['endDate'])->toBe('2026-03-02 23:59:59')
        ->and($graphData['graph']['datasets'][0]['data'])->toBe([100.0])
        ->and($graphData['data']['ordersAmount'])->toBe(1);
});

it('rekent na het kiezen van een periode met die periode', function () {
    $vorigeMaand = now()->subMonthNoOverflow();

    makeGraphOrder($vorigeMaand->copy()->startOfMonth()->addDays(2)->toDateTimeString(), 200);
    makeGraphOrder(now()->toDateTimeString(), 999);

    $graphData = Livewire::test(RevenueStatisticsPage::class)
        ->set('data.period', 'last_month')
        ->get('graphData');

    // De periode zet de datumvelden, en de berekening moet daarna draaien. Deed
    // hij dat ervoor, dan stonden de velden op vorige maand terwijl de cijfers
    // over de laatste dertig dagen gingen, inclusief de order van vandaag.
    expect($graphData['filters']['beginDate'])->toBe($vorigeMaand->copy()->startOfMonth()->startOfDay()->toDateTimeString())
        ->and($graphData['filters']['endDate'])->toBe($vorigeMaand->copy()->endOfMonth()->endOfDay()->toDateTimeString())
        ->and($graphData['data']['ordersAmount'])->toBe(1)
        ->and(array_sum($graphData['graph']['datasets'][0]['data']))->toBe(200.0);
});
