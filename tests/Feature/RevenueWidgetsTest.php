<?php

use App\Models\User;
use Livewire\Livewire;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueChart;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']), 'sanctum');
});

it('toont de grafiek zonder gegevens zonder eruit te klappen', function () {
    // De widgets krijgen hun cijfers van de pagina. Kwamen die er (nog) niet,
    // dan las de grafiek een ontbrekende sleutel uit en gaf de widget een fout
    // in plaats van een lege lijn.
    Livewire::test(RevenueChart::class)->assertOk();
    Livewire::test(RevenueCards::class)->assertOk();
});

it('neemt nieuwe cijfers over uit de gebeurtenis van de pagina', function () {
    $graphData = [
        'graph' => [
            'datasets' => [['label' => 'Omzet', 'data' => [10.0, 20.0]]],
            'labels' => ['01-03-2026', '02-03-2026'],
        ],
        'data' => [
            'ordersAmount' => 2,
            'orderAmount' => '€ 30,00',
            'averageOrderAmount' => '€ 15,00',
            'productsSold' => 3,
            'paymentCostsAmount' => '€ 0,00',
            'shippingCostsAmount' => '€ 0,00',
            'discountAmount' => '€ 0,00',
            'btwAmount' => '€ 5,00',
        ],
    ];

    // Zonder deze weg terug moesten de widgets elke seconde blijven pollen om
    // een filterwijziging op te pikken.
    Livewire::test(RevenueChart::class)
        ->dispatch('updateGraphData', $graphData)
        ->assertOk()
        ->assertSet('graphData.graph.labels.1', '02-03-2026');

    Livewire::test(RevenueCards::class)
        ->dispatch('updateGraphData', $graphData)
        ->assertOk()
        ->assertSee('€ 30,00');
});
