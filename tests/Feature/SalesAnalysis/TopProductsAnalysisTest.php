<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\TopProductsAnalysis;

function toppersContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('sorteert op omzet en op aantallen apart', function () {
    $duur = AnalysisFixtures::product('Duur product');
    $goedkoop = AnalysisFixtures::product('Goedkoop product');

    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => $duur, 'quantity' => 1, 'price' => 500.0],
        ['product' => $goedkoop, 'quantity' => 50, 'price' => 100.0],
    ]);

    $facts = (new TopProductsAnalysis())->run(toppersContext())->facts;

    expect($facts['by_revenue'][0]['name'])->toBe('Duur product')
        ->and($facts['by_revenue'][0]['revenue'])->toBe(500.0)
        ->and($facts['by_units'][0]['name'])->toBe('Goedkoop product')
        ->and($facts['by_units'][0]['units'])->toBe(50);
});

it('telt meerdere orders per product bij elkaar op', function () {
    $a = AnalysisFixtures::product('Product A');

    AnalysisFixtures::paidOrder('2026-03-05', [['product' => $a, 'quantity' => 2, 'price' => 30.0]]);
    AnalysisFixtures::paidOrder('2026-03-25', [['product' => $a, 'quantity' => 3, 'price' => 45.0]]);

    $facts = (new TopProductsAnalysis())->run(toppersContext())->facts;

    expect($facts['by_revenue'])->toHaveCount(1)
        ->and($facts['by_revenue'][0]['revenue'])->toBe(75.0)
        ->and($facts['by_revenue'][0]['units'])->toBe(5);
});

it('meldt een product dat veel stuks doet maar weinig omzet', function () {
    // Staat bij de eerste drie op aantallen en buiten de eerste helft op
    // omzet: dat is een product dat het volume draagt maar niet de kassa.
    $volume = AnalysisFixtures::product('Volumeproduct');

    AnalysisFixtures::paidOrder('2026-03-05', [['product' => $volume, 'quantity' => 100, 'price' => 50.0]]);
    foreach (range(1, 5) as $i) {
        $duur = AnalysisFixtures::product("Duur product {$i}");
        AnalysisFixtures::paidOrder('2026-03-06', [['product' => $duur, 'quantity' => 1, 'price' => 500.0]]);
    }

    $signals = (new TopProductsAnalysis())->run(toppersContext())->signals;

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->severity)->toBe(Signal::OPPORTUNITY)
        ->and($signals[0]->title)->toContain('Volumeproduct');
});

it('geeft lege lijsten terug zonder verkopen', function () {
    $result = (new TopProductsAnalysis())->run(toppersContext());

    expect($result->facts['by_revenue'])->toBe([])
        ->and($result->facts['by_units'])->toBe([])
        ->and($result->signals)->toBe([]);
});
