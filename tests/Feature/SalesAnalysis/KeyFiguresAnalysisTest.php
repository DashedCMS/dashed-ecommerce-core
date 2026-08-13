<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\KeyFiguresAnalysis;

function keyFiguresContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('telt omzet, orders, stuks en unieke producten in de periode', function () {
    $a = AnalysisFixtures::product('Product A');
    $b = AnalysisFixtures::product('Product B');

    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => $a, 'quantity' => 2, 'price' => 40.0],
        ['product' => $b, 'quantity' => 1, 'price' => 10.0],
    ]);
    AnalysisFixtures::paidOrder('2026-03-20', [
        ['product' => $a, 'quantity' => 1, 'price' => 20.0],
    ]);

    $facts = (new KeyFiguresAnalysis())->run(keyFiguresContext())->facts;

    expect($facts['current']['revenue'])->toBe(70.0)
        ->and($facts['current']['orders'])->toBe(2)
        ->and($facts['current']['units'])->toBe(4)
        ->and($facts['current']['unique_products'])->toBe(2)
        ->and($facts['current']['average_order_value'])->toBe(35.0);
});

it('laat orders buiten de periode buiten beeld', function () {
    $a = AnalysisFixtures::product('Product A');

    AnalysisFixtures::paidOrder('2026-02-28', [['product' => $a, 'quantity' => 1, 'price' => 99.0]]);
    AnalysisFixtures::paidOrder('2026-04-01', [['product' => $a, 'quantity' => 1, 'price' => 99.0]]);
    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 10.0]]);

    $facts = (new KeyFiguresAnalysis())->run(keyFiguresContext())->facts;

    expect($facts['current']['revenue'])->toBe(10.0)
        ->and($facts['current']['orders'])->toBe(1);
});

it('telt de vorige periode en vorig jaar apart', function () {
    $a = AnalysisFixtures::product('Product A');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);
    // De vorige periode loopt van 29 januari tot en met 28 februari.
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 60.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 80.0]]);

    $facts = (new KeyFiguresAnalysis())->run(keyFiguresContext())->facts;

    expect($facts['current']['revenue'])->toBe(100.0)
        ->and($facts['previous']['revenue'])->toBe(60.0)
        ->and($facts['last_year']['revenue'])->toBe(80.0);
});

it('telt orders van een andere site niet mee', function () {
    $a = AnalysisFixtures::product('Product A');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 10.0]]);
    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 999.0]], siteId: 'andere-site');

    expect((new KeyFiguresAnalysis())->run(keyFiguresContext())->facts['current']['revenue'])->toBe(10.0);
});

it('meldt een omzetdaling die tegen beide vergelijkingen tegenvalt', function () {
    $a = AnalysisFixtures::product('Product A');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 50.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 200.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 200.0]]);

    $signals = (new KeyFiguresAnalysis())->run(keyFiguresContext())->signals;

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->severity)->toBe(Signal::ATTENTION)
        ->and($signals[0]->title)->toContain('Omzet');
});

it('meldt niets wanneer de omzet dicht bij beide vergelijkingen ligt', function () {
    $a = AnalysisFixtures::product('Product A');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 105.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 95.0]]);

    expect((new KeyFiguresAnalysis())->run(keyFiguresContext())->signals)->toBe([]);
});

it('meldt niets wanneer alleen de vorige periode tegenvalt, want dat is seizoen', function () {
    $a = AnalysisFixtures::product('Product A');

    // 100 tegen 400 is 75 procent omlaag, maar 100 tegen 105 blijft binnen de
    // marge. Beide vergelijkingsbedragen liggen boven de ondergrens van 100,
    // dus deviation() geeft hier twee echte getallen terug en niet null.
    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 400.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 105.0]]);

    expect((new KeyFiguresAnalysis())->run(keyFiguresContext())->signals)->toBe([]);
});

it('meldt niets wanneer alleen vorig jaar tegenvalt, want dat is een jaartrend', function () {
    $a = AnalysisFixtures::product('Product A');

    // Het spiegelbeeld: de vorige periode ligt vlak, vorig jaar ligt ver weg.
    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 105.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 400.0]]);

    expect((new KeyFiguresAnalysis())->run(keyFiguresContext())->signals)->toBe([]);
});

it('meldt niets zonder omzet in de vergelijkingsperiodes, in plaats van door nul te delen', function () {
    $a = AnalysisFixtures::product('Product A');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);

    $result = (new KeyFiguresAnalysis())->run(keyFiguresContext());

    expect($result->signals)->toBe([])
        ->and($result->facts['previous']['revenue'])->toBe(0.0)
        ->and($result->facts['current']['average_order_value'])->toBe(100.0);
});
