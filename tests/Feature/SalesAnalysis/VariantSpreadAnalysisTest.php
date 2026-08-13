<?php

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\VariantSpreadAnalysis;

function variantenContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('vindt de groep waarin één variant vrijwel alles doet', function () {
    $groep = AnalysisFixtures::productGroup('Veluro');
    $loper = AnalysisFixtures::product('Veluro Zwart', group: $groep);
    $stil = AnalysisFixtures::product('Veluro Rood', group: $groep);
    AnalysisFixtures::product('Veluro Blauw', group: $groep);

    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => $loper, 'quantity' => 20, 'price' => 950.0],
        ['product' => $stil, 'quantity' => 1, 'price' => 50.0],
    ]);

    $groups = (new VariantSpreadAnalysis())->run(variantenContext())->facts['groups'];

    expect($groups)->toHaveCount(1)
        ->and($groups[0]['name'])->toBe('Veluro')
        ->and($groups[0]['variants'])->toBe(3)
        ->and($groups[0]['sold_variants'])->toBe(2)
        ->and($groups[0]['dominant_name'])->toBe('Veluro Zwart')
        ->and($groups[0]['dominant_share_pct'])->toBe(95.0);
});

it('slaat groepen met maar één variant over', function () {
    $groep = AnalysisFixtures::productGroup('Enkeling');
    $enige = AnalysisFixtures::product('Enige variant', group: $groep);

    AnalysisFixtures::paidOrder('2026-03-05', [['product' => $enige, 'quantity' => 1, 'price' => 100.0]]);

    expect((new VariantSpreadAnalysis())->run(variantenContext())->facts['groups'])->toBe([]);
});

it('meldt een groep waarin de rest van de varianten stilstaat', function () {
    $groep = AnalysisFixtures::productGroup('Veluro');
    $loper = AnalysisFixtures::product('Veluro Zwart', group: $groep);
    AnalysisFixtures::product('Veluro Rood', group: $groep);
    AnalysisFixtures::product('Veluro Blauw', group: $groep);

    AnalysisFixtures::paidOrder('2026-03-05', [['product' => $loper, 'quantity' => 20, 'price' => 1000.0]]);

    $signals = (new VariantSpreadAnalysis())->run(variantenContext())->signals;

    expect($signals)->toHaveCount(1)
        ->and($signals[0]->severity)->toBe(Signal::OPPORTUNITY)
        ->and($signals[0]->title)->toContain('Veluro');
});

it('meldt niets wanneer de varianten redelijk verdeeld verkopen', function () {
    $groep = AnalysisFixtures::productGroup('Verdeeld');
    $een = AnalysisFixtures::product('Variant 1', group: $groep);
    $twee = AnalysisFixtures::product('Variant 2', group: $groep);

    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => $een, 'quantity' => 1, 'price' => 500.0],
        ['product' => $twee, 'quantity' => 1, 'price' => 500.0],
    ]);

    expect((new VariantSpreadAnalysis())->run(variantenContext())->signals)->toBe([]);
});

it('telt een variant die na de bestelling verwijderd is ook niet meer als verkocht', function () {
    $groep = AnalysisFixtures::productGroup('Veluro');
    $loper = AnalysisFixtures::product('Veluro Zwart', group: $groep);
    $tweede = AnalysisFixtures::product('Veluro Rood', group: $groep);
    $verwijderd = AnalysisFixtures::product('Veluro Blauw', group: $groep);

    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => $loper, 'quantity' => 10, 'price' => 900.0],
        ['product' => $tweede, 'quantity' => 1, 'price' => 60.0],
        ['product' => $verwijderd, 'quantity' => 1, 'price' => 40.0],
    ]);

    Product::withoutEvents(fn () => $verwijderd->delete());

    $groups = (new VariantSpreadAnalysis())->run(variantenContext())->facts['groups'];

    // De telling en de verkoopregels moeten dezelfde populatie beschrijven:
    // de verwijderde variant telt niet meer als variant, dus mag hij ook
    // niet meer als verkochte variant meetellen.
    expect($groups)->toHaveCount(1)
        ->and($groups[0]['variants'])->toBe(2)
        ->and($groups[0]['sold_variants'])->toBe(2)
        ->and($groups[0]['sold_variants'])->toBeLessThanOrEqual($groups[0]['variants']);
});

it('telt varianten van een andere site niet mee', function () {
    $groep = AnalysisFixtures::productGroup('Multisite');
    $hier = AnalysisFixtures::product('Multisite Hier', group: $groep, siteId: 'site');
    AnalysisFixtures::product('Multisite Daar', group: $groep, siteId: 'andere-site');

    AnalysisFixtures::paidOrder('2026-03-05', [
        ['product' => $hier, 'quantity' => 1, 'price' => 100.0],
    ], siteId: 'site');

    $groups = (new VariantSpreadAnalysis())->run(variantenContext())->facts['groups'];

    // Zonder de tweede variant op site 'site' telt de groep maar één
    // variant voor deze context en valt hij dus buiten de resultaten, ook
    // al bestaat er wel degelijk een tweede variant in de database.
    expect($groups)->toBe([]);
});
