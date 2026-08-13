<?php

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedEcommerceCore\Support\Analysis\Signal;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisReport;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisNarrator;

function narratorReport(): SalesAnalysisReport
{
    return new SalesAnalysisReport(
        results: ['kerncijfers' => new AnalysisResult(
            facts: ['current' => ['revenue' => 100.0]],
            signals: [new Signal(Signal::URGENT, 'Voorraad raakt op', 'Nog 3 dagen', ['Dagen' => 3])],
        )],
        failed: [],
    );
}

function narratorContext(): AnalysisContext
{
    return AnalysisContext::for(AnalysisPeriod::make('2026-03-01', '2026-03-31'), 'site');
}

it('geeft het verhaal van het model terug', function () {
    Ai::shouldReceive('hasProvider')->andReturnTrue();
    Ai::shouldReceive('text')->once()->andReturn('  Let deze maand op de voorraad.  ');

    expect(SalesAnalysisNarrator::narrate(narratorReport(), narratorContext()))
        ->toBe('Let deze maand op de voorraad.');
});

it('stuurt de signalen mee en niet de ruwe cijfers', function () {
    Ai::shouldReceive('hasProvider')->andReturnTrue();
    Ai::shouldReceive('text')->once()->withArgs(function (string $prompt) {
        return str_contains($prompt, 'Voorraad raakt op')
            && str_contains($prompt, 'Nog 3 dagen')
            && ! str_contains($prompt, 'kerncijfers');
    })->andReturn('Verhaal');

    expect(SalesAnalysisNarrator::narrate(narratorReport(), narratorContext()))->toBe('Verhaal');
});

it('geeft niets terug zonder AI-provider', function () {
    Ai::shouldReceive('hasProvider')->andReturnFalse();
    Ai::shouldReceive('text')->never();

    expect(SalesAnalysisNarrator::narrate(narratorReport(), narratorContext()))->toBeNull();
});

it('geeft niets terug wanneer de aanroep klapt', function () {
    Ai::shouldReceive('hasProvider')->andReturnTrue();
    Ai::shouldReceive('text')->andThrow(new RuntimeException('Geen krediet'));

    expect(SalesAnalysisNarrator::narrate(narratorReport(), narratorContext()))->toBeNull();
});

it('geeft niets terug wanneer het model een lege tekst teruggeeft', function () {
    Ai::shouldReceive('hasProvider')->andReturnTrue();
    Ai::shouldReceive('text')->andReturn('   ');

    expect(SalesAnalysisNarrator::narrate(narratorReport(), narratorContext()))->toBeNull();
});

it('vraagt niets aan het model zonder signalen', function () {
    Ai::shouldReceive('hasProvider')->andReturnTrue();
    Ai::shouldReceive('text')->never();

    $leeg = new SalesAnalysisReport(results: [], failed: []);

    expect(SalesAnalysisNarrator::narrate($leeg, narratorContext()))->toBeNull();
});
