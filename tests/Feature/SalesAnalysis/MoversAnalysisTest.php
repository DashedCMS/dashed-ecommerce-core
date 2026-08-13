<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\MoversAnalysis;

function moversContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('zet een gezakt product bij de dalers met beide vergelijkingen erbij', function () {
    $a = AnalysisFixtures::product('Zakker');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 400.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 300.0]]);

    $facts = (new MoversAnalysis())->run(moversContext())->facts;

    expect($facts['fallers'])->toHaveCount(1)
        ->and($facts['fallers'][0]['name'])->toBe('Zakker')
        ->and($facts['fallers'][0]['revenue'])->toBe(100.0)
        ->and($facts['fallers'][0]['previous_revenue'])->toBe(400.0)
        ->and($facts['fallers'][0]['last_year_revenue'])->toBe(300.0)
        ->and($facts['fallers'][0]['change_pct'])->toBe(-75.0)
        ->and($facts['risers'])->toBe([]);
});

it('zet een gestegen product bij de stijgers', function () {
    $a = AnalysisFixtures::product('Stijger');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 400.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);

    $facts = (new MoversAnalysis())->run(moversContext())->facts;

    expect($facts['risers'])->toHaveCount(1)
        ->and($facts['risers'][0]['change_pct'])->toBe(300.0);
});

it('negeert bewegingen op te kleine bedragen', function () {
    // Van 5 naar 20 euro is 300 procent en betekent niets.
    $a = AnalysisFixtures::product('Kruimel');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 20.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 5.0]]);

    $facts = (new MoversAnalysis())->run(moversContext())->facts;

    expect($facts['risers'])->toBe([])
        ->and($facts['fallers'])->toBe([]);
});

it('behandelt een product dat vorige periode nog niet verkocht als nieuw en niet als oneindige stijging', function () {
    $a = AnalysisFixtures::product('Nieuw');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 500.0]]);

    $facts = (new MoversAnalysis())->run(moversContext())->facts;

    expect($facts['risers'])->toHaveCount(1)
        ->and($facts['risers'][0]['change_pct'])->toBeNull()
        ->and($facts['risers'][0]['previous_revenue'])->toBe(0.0);
});

it('meldt een daler die tegen beide vergelijkingen zakt als aandachtspunt', function () {
    $a = AnalysisFixtures::product('Zakker');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 400.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 300.0]]);

    $signals = (new MoversAnalysis())->run(moversContext())->signals;

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->severity)->toBe(Signal::ATTENTION)
        ->and($signals[0]->title)->toContain('Zakker');
});

it('staat wel bij de dalers maar geeft geen signaal als het tegen vorig jaar niet slechter presteert', function () {
    // Deze periode 100, vorige periode 400: een harde daling. Maar vorig
    // jaar deed dit product het met 80 nog slechter, dus dit is seizoen
    // en geen aandachtspunt.
    $a = AnalysisFixtures::product('Seizoensproduct');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 100.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 400.0]]);
    AnalysisFixtures::paidOrder('2025-03-15', [['product' => $a, 'quantity' => 1, 'price' => 80.0]]);

    $result = (new MoversAnalysis())->run(moversContext());

    expect($result->facts['fallers'])->toHaveCount(1)
        ->and($result->facts['fallers'][0]['name'])->toBe('Seizoensproduct')
        ->and($result->signals)->toBe([]);
});

it('telt een daler mee zodra één kant boven de drempel zit, ook als de andere kant eronder ligt', function () {
    // Deze periode 5 euro, vorige periode 400 euro: de omzet nu is klein,
    // maar de vorige periode was dat niet. Dat is een echte daler en hoort
    // niet weggefilterd te worden door de ondergrens.
    $a = AnalysisFixtures::product('Bijnaniets');

    AnalysisFixtures::paidOrder('2026-03-15', [['product' => $a, 'quantity' => 1, 'price' => 5.0]]);
    AnalysisFixtures::paidOrder('2026-02-10', [['product' => $a, 'quantity' => 1, 'price' => 400.0]]);

    $facts = (new MoversAnalysis())->run(moversContext())->facts;

    expect($facts['fallers'])->toHaveCount(1)
        ->and($facts['fallers'][0]['name'])->toBe('Bijnaniets');
});
