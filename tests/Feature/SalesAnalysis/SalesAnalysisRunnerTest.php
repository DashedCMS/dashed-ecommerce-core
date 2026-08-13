<?php

use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisRunner;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisRegistry;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

afterEach(fn () => SalesAnalysisRegistry::fakeMap(null));

function runnerContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

class RunnerGoodAnalysis implements SalesAnalysis
{
    public static function key(): string
    {
        return 'goed';
    }

    public static function label(): string
    {
        return 'Goede analyse';
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
        return new AnalysisResult(
            facts: ['omzet' => 100.0],
            signals: [new Signal(Signal::OPPORTUNITY, 'Kansje', 'Uitleg')],
        );
    }
}

class RunnerUrgentAnalysis implements SalesAnalysis
{
    public static function key(): string
    {
        return 'urgent';
    }

    public static function label(): string
    {
        return 'Urgente analyse';
    }

    public static function group(): string
    {
        return 'assortiment';
    }

    public static function isAvailable(AnalysisContext $context): bool
    {
        return true;
    }

    public function run(AnalysisContext $context): AnalysisResult
    {
        return new AnalysisResult(signals: [new Signal(Signal::URGENT, 'Brand', 'Uitleg')]);
    }
}

class RunnerBrokenAnalysis implements SalesAnalysis
{
    public static function key(): string
    {
        return 'kapot';
    }

    public static function label(): string
    {
        return 'Kapotte analyse';
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
        throw new RuntimeException('De query klapte');
    }
}

class RunnerUnavailableAnalysis implements SalesAnalysis
{
    public static function key(): string
    {
        return 'nietbeschikbaar';
    }

    public static function label(): string
    {
        return 'Niet beschikbaar';
    }

    public static function group(): string
    {
        return 'marge';
    }

    public static function isAvailable(AnalysisContext $context): bool
    {
        return false;
    }

    public function run(AnalysisContext $context): AnalysisResult
    {
        throw new RuntimeException('Had niet gedraaid mogen worden');
    }
}

it('draait alle beschikbare analyses en bewaart hun feiten', function () {
    SalesAnalysisRegistry::fakeMap(['goed' => RunnerGoodAnalysis::class]);

    $report = (new SalesAnalysisRunner())->run(runnerContext());

    expect($report->resultFor('goed')->facts)->toBe(['omzet' => 100.0])
        ->and($report->failed)->toBe([]);
});

it('laat de andere analyses staan wanneer er een klapt', function () {
    SalesAnalysisRegistry::fakeMap([
        'kapot' => RunnerBrokenAnalysis::class,
        'goed' => RunnerGoodAnalysis::class,
    ]);

    $report = (new SalesAnalysisRunner())->run(runnerContext());

    expect($report->resultFor('goed'))->not->toBeNull()
        ->and($report->resultFor('kapot'))->toBeNull()
        ->and($report->failed)->toHaveKey('kapot');
});

it('slaat een analyse over die op deze shop niets kan zeggen', function () {
    SalesAnalysisRegistry::fakeMap(['nietbeschikbaar' => RunnerUnavailableAnalysis::class]);

    $report = (new SalesAnalysisRunner())->run(runnerContext());

    expect($report->results)->toBe([])
        ->and($report->failed)->toBe([]);
});

it('zet de zwaarste signalen bovenaan, ongeacht de volgorde van de analyses', function () {
    SalesAnalysisRegistry::fakeMap([
        'goed' => RunnerGoodAnalysis::class,
        'urgent' => RunnerUrgentAnalysis::class,
    ]);

    $signals = (new SalesAnalysisRunner())->run(runnerContext())->signals();

    expect($signals)->toHaveCount(2)
        ->and($signals[0]->title)->toBe('Brand')
        ->and($signals[1]->title)->toBe('Kansje');
});
