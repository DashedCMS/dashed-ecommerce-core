<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\ConcentrationAnalysis;

function concentratieContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('telt hoeveel producten samen de eerste helft van de omzet maken', function () {
    // Eén product van 600 en vier van 100: het eerste product doet al meer
    // dan de helft van de 1000.
    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => AnalysisFixtures::product('Groot'), 'quantity' => 1, 'price' => 600.0],
        ['product' => AnalysisFixtures::product('Klein 1'), 'quantity' => 1, 'price' => 100.0],
        ['product' => AnalysisFixtures::product('Klein 2'), 'quantity' => 1, 'price' => 100.0],
        ['product' => AnalysisFixtures::product('Klein 3'), 'quantity' => 1, 'price' => 100.0],
        ['product' => AnalysisFixtures::product('Klein 4'), 'quantity' => 1, 'price' => 100.0],
    ]);

    $facts = (new ConcentrationAnalysis())->run(concentratieContext())->facts;

    expect($facts['products_sold'])->toBe(5)
        ->and($facts['products_for_half'])->toBe(1)
        ->and($facts['top_share_pct'])->toBe(60.0);
});

it('meldt het wanneer een handvol producten bijna alles draagt', function () {
    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => AnalysisFixtures::product('Groot'), 'quantity' => 1, 'price' => 900.0],
        ['product' => AnalysisFixtures::product('Klein 1'), 'quantity' => 1, 'price' => 25.0],
        ['product' => AnalysisFixtures::product('Klein 2'), 'quantity' => 1, 'price' => 25.0],
        ['product' => AnalysisFixtures::product('Klein 3'), 'quantity' => 1, 'price' => 25.0],
        ['product' => AnalysisFixtures::product('Klein 4'), 'quantity' => 1, 'price' => 25.0],
    ]);

    $signals = (new ConcentrationAnalysis())->run(concentratieContext())->signals;

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->severity)->toBe(Signal::ATTENTION);
});

it('zegt niets bij te weinig verkochte producten om over concentratie te praten', function () {
    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => AnalysisFixtures::product('Enige'), 'quantity' => 1, 'price' => 900.0],
    ]);

    $result = (new ConcentrationAnalysis())->run(concentratieContext());

    expect($result->facts['products_sold'])->toBe(1)
        ->and($result->signals)->toBe([]);
});

it('zegt niets als de omzet gelijk verdeeld is over genoeg producten', function () {
    // Vijf producten van gelijke waarde: de bovenste vijfde draagt maar 20%,
    // ver onder de drempel voor een signaal.
    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => AnalysisFixtures::product('Een'), 'quantity' => 1, 'price' => 100.0],
        ['product' => AnalysisFixtures::product('Twee'), 'quantity' => 1, 'price' => 100.0],
        ['product' => AnalysisFixtures::product('Drie'), 'quantity' => 1, 'price' => 100.0],
        ['product' => AnalysisFixtures::product('Vier'), 'quantity' => 1, 'price' => 100.0],
        ['product' => AnalysisFixtures::product('Vijf'), 'quantity' => 1, 'price' => 100.0],
    ]);

    $result = (new ConcentrationAnalysis())->run(concentratieContext());

    expect($result->facts['top_share_pct'])->toBe(20.0)
        ->and($result->signals)->toBe([]);
});

it('deelt niet door nul zonder verkopen', function () {
    $facts = (new ConcentrationAnalysis())->run(concentratieContext())->facts;

    expect($facts['products_sold'])->toBe(0)
        ->and($facts['products_for_half'])->toBe(0)
        ->and($facts['top_share_pct'])->toBe(0.0);
});
