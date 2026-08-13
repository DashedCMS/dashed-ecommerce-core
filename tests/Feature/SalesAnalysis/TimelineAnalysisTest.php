<?php

use Dashed\DashedEcommerceCore\Tests\Support\AnalysisFixtures;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Analyses\TimelineAnalysis;

it('geeft een punt per dag voor een korte periode, ook voor dagen zonder omzet', function () {
    $a = AnalysisFixtures::product('Product A');
    AnalysisFixtures::paidOrder('2026-03-02', [['product' => $a, 'quantity' => 1, 'price' => 30.0]]);

    $context = AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-05'), 'site');
    $facts = (new TimelineAnalysis())->run($context)->facts;

    expect($facts['interval'])->toBe('day')
        ->and($facts['labels'])->toHaveCount(5)
        ->and($facts['revenue'])->toBe([0.0, 30.0, 0.0, 0.0, 0.0]);
});

it('schakelt over op weken voor een lange periode', function () {
    $context = AnalysisContext::for(AnalysisPeriod::make('2026-01-01', '2026-06-30'), 'site');
    $facts = (new TimelineAnalysis())->run($context)->facts;

    expect($facts['interval'])->toBe('week')
        ->and(count($facts['labels']))->toBe(count($facts['revenue']))
        ->and(count($facts['labels']))->toBeLessThan(40);
});

it('telt meerdere orders op dezelfde dag bij elkaar op', function () {
    $a = AnalysisFixtures::product('Product A');
    AnalysisFixtures::paidOrder('2026-03-02', [['product' => $a, 'quantity' => 1, 'price' => 30.0]]);
    AnalysisFixtures::paidOrder('2026-03-02', [['product' => $a, 'quantity' => 1, 'price' => 20.0]]);

    $context = AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-03'), 'site');

    expect((new TimelineAnalysis())->run($context)->facts['revenue'])->toBe([0.0, 50.0, 0.0]);
});

it('levert geen signalen op, want dit is een grafiek', function () {
    $context = AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-05'), 'site');

    expect((new TimelineAnalysis())->run($context)->signals)->toBe([]);
});
