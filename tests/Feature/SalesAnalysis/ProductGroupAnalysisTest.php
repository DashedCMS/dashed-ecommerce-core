<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\ProductGroupAnalysis;

function groepenContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('telt omzet per groep en rekent het aandeel uit', function () {
    $groot = AnalysisFixtures::productGroup('Grote groep');
    $klein = AnalysisFixtures::productGroup('Kleine groep');

    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => AnalysisFixtures::product('A', group: $groot), 'quantity' => 1, 'price' => 750.0],
        ['product' => AnalysisFixtures::product('B', group: $klein), 'quantity' => 1, 'price' => 250.0],
    ]);

    $groups = (new ProductGroupAnalysis())->run(groepenContext())->facts['groups'];

    expect($groups)->toHaveCount(2)
        ->and($groups[0]['name'])->toBe('Grote groep')
        ->and($groups[0]['revenue'])->toBe(750.0)
        ->and($groups[0]['share_pct'])->toBe(75.0)
        ->and($groups[1]['share_pct'])->toBe(25.0);
});

it('meldt een groep die duidelijk aandeel verliest', function () {
    $zakt = AnalysisFixtures::productGroup('Zakkende groep');
    $stijgt = AnalysisFixtures::productGroup('Stijgende groep');
    $zakkend = AnalysisFixtures::product('Z', group: $zakt);
    $stijgend = AnalysisFixtures::product('S', group: $stijgt);

    // Vorige periode: 80% voor de zakkende groep.
    AnalysisFixtures::paidOrder('2026-02-10', [
        ['product' => $zakkend, 'quantity' => 1, 'price' => 800.0],
        ['product' => $stijgend, 'quantity' => 1, 'price' => 200.0],
    ]);
    // Deze periode: nog 20%.
    AnalysisFixtures::paidOrder('2026-03-10', [
        ['product' => $zakkend, 'quantity' => 1, 'price' => 200.0],
        ['product' => $stijgend, 'quantity' => 1, 'price' => 800.0],
    ]);

    $signals = (new ProductGroupAnalysis())->run(groepenContext())->signals;

    expect($signals)->not->toBeEmpty()
        ->and($signals[0]->severity)->toBe(Signal::ATTENTION)
        ->and($signals[0]->title)->toContain('Zakkende groep');
});

it('geeft een lege lijst zonder verkopen en deelt niet door nul', function () {
    $result = (new ProductGroupAnalysis())->run(groepenContext());

    expect($result->facts['groups'])->toBe([])
        ->and($result->signals)->toBe([]);
});
