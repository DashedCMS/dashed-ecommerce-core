<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\UnsoldStockAnalysis;

function stilleContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('vindt producten met voorraad die niets verkochten', function () {
    $stil = AnalysisFixtures::product('Stille voorraad', price: 25.0, stock: 4);
    $loper = AnalysisFixtures::product('Loper', price: 10.0, stock: 3);

    AnalysisFixtures::paidOrder('2026-03-10', [['product' => $loper, 'quantity' => 1, 'price' => 10.0]]);

    $facts = (new UnsoldStockAnalysis())->run(stilleContext())->facts;

    expect($facts['products'])->toHaveCount(1)
        ->and($facts['products'][0]['name'])->toBe('Stille voorraad')
        ->and($facts['products'][0]['stock'])->toBe(4)
        ->and($facts['products'][0]['stock_value'])->toBe(100.0)
        ->and($facts['total_value'])->toBe(100.0);
});

it('negeert producten zonder voorraad', function () {
    AnalysisFixtures::product('Geen voorraad', price: 25.0, stock: 0);

    expect((new UnsoldStockAnalysis())->run(stilleContext())->facts['products'])->toBe([]);
});

it('meldt stilliggend kapitaal boven de drempel', function () {
    AnalysisFixtures::product('Dure plank', price: 500.0, stock: 10);

    $signals = (new UnsoldStockAnalysis())->run(stilleContext())->signals;

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->severity)->toBe(Signal::ATTENTION)
        ->and($signals[0]->title)->toContain('voorraad');
});

it('meldt niets wanneer er nauwelijks kapitaal stilligt', function () {
    AnalysisFixtures::product('Kleinigheid', price: 1.0, stock: 2);

    expect((new UnsoldStockAnalysis())->run(stilleContext())->signals)->toBe([]);
});
