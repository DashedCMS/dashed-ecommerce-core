<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisRegistry;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

afterEach(fn () => SalesAnalysisRegistry::fakeMap(null));

class RegistryDummyAnalysis implements SalesAnalysis
{
    public static function key(): string
    {
        return 'dummy';
    }

    public static function label(): string
    {
        return 'Dummy';
    }

    public static function group(): string
    {
        return 'verkoop';
    }

    public static function isAvailable(AnalysisContext $context): bool
    {
        return true;
    }

    public function run(AnalysisContext $context): AnalysisResult
    {
        return AnalysisResult::empty();
    }
}

it('bouwt de context met beide vergelijkingsperiodes', function () {
    $context = AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');

    expect($context->siteId)->toBe('site')
        ->and($context->previous->end->toDateString())->toBe('2026-02-28')
        ->and($context->lastYear->start->toDateString())->toBe('2025-03-01');
});

it('weegt urgent zwaarder dan aandacht en aandacht zwaarder dan kans', function () {
    expect(Signal::weight(Signal::URGENT))->toBeGreaterThan(Signal::weight(Signal::ATTENTION))
        ->and(Signal::weight(Signal::ATTENTION))->toBeGreaterThan(Signal::weight(Signal::OPPORTUNITY))
        ->and(Signal::weight('onzin'))->toBe(0);
});

it('haalt aangemelde analyses op, op sleutel', function () {
    cms()->clearBuilder('salesAnalyses');
    cms()->builder('salesAnalyses', [RegistryDummyAnalysis::class]);

    expect(SalesAnalysisRegistry::map())->toBe(['dummy' => RegistryDummyAnalysis::class]);
});

it('negeert wat geen bestaande klasse is of het contract niet implementeert', function () {
    cms()->clearBuilder('salesAnalyses');
    cms()->builder('salesAnalyses', [
        RegistryDummyAnalysis::class,
        'Deze\\Klasse\\Bestaat\\Niet',
        \stdClass::class,
        42,
    ]);

    expect(SalesAnalysisRegistry::map())->toBe(['dummy' => RegistryDummyAnalysis::class]);
});

it('laat een test de kaart forceren', function () {
    SalesAnalysisRegistry::fakeMap(['verzonnen' => RegistryDummyAnalysis::class]);

    expect(SalesAnalysisRegistry::map())->toBe(['verzonnen' => RegistryDummyAnalysis::class]);
});
