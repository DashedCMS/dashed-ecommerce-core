<?php

use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;

it('telt beide einddagen mee in de lengte', function () {
    expect(AnalysisPeriod::make('2026-03-01', '2026-03-31')->days())->toBe(31);
    expect(AnalysisPeriod::make('2026-03-01', '2026-03-01')->days())->toBe(1);
});

it('legt de vorige periode direct ervoor met dezelfde lengte', function () {
    // Maart heeft 31 dagen, dus de vorige periode loopt 31 dagen terug vanaf
    // 28 februari. Dat is bewust niet "vorige maand": februari heeft er 28.
    $previous = AnalysisPeriod::make('2026-03-01', '2026-03-31')->previous();

    expect($previous->start->toDateString())->toBe('2026-01-29')
        ->and($previous->end->toDateString())->toBe('2026-02-28')
        ->and($previous->days())->toBe(31);
});

it('legt vorig jaar op dezelfde kalenderdatums', function () {
    $lastYear = AnalysisPeriod::make('2026-03-01', '2026-03-31')->lastYear();

    expect($lastYear->start->toDateString())->toBe('2025-03-01')
        ->and($lastYear->end->toDateString())->toBe('2025-03-31');
});

it('zakt terug naar het einde van februari als 29 februari vorig jaar niet bestond', function () {
    // Carbon's subYear() laat 2024-02-29 doorlopen naar 2023-03-01, wat de
    // periode een dag te ver zou leggen. Daarom subYearNoOverflow().
    $lastYear = AnalysisPeriod::make('2024-02-01', '2024-02-29')->lastYear();

    expect($lastYear->start->toDateString())->toBe('2023-02-01')
        ->and($lastYear->end->toDateString())->toBe('2023-02-28');
});

it('beslaat de hele begin- en einddag', function () {
    $period = AnalysisPeriod::make('2026-03-01', '2026-03-31');

    expect($period->start->format('H:i:s'))->toBe('00:00:00')
        ->and($period->end->format('H:i:s'))->toBe('23:59:59');
});

it('geeft dezelfde periode dezelfde cachesleutel en een andere periode een andere', function () {
    $a = AnalysisPeriod::make('2026-03-01', '2026-03-31');
    $b = AnalysisPeriod::make('2026-03-01', '2026-03-31');
    $c = AnalysisPeriod::make('2026-03-01', '2026-03-30');

    expect($a->cacheKey())->toBe($b->cacheKey())
        ->and($a->cacheKey())->not->toBe($c->cacheKey());
});

it('weigert een eind dat voor het begin ligt', function () {
    expect(fn () => AnalysisPeriod::make('2026-03-31', '2026-03-01'))
        ->toThrow(InvalidArgumentException::class);
});
