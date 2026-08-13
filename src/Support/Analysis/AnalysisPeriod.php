<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * De periode waarover geanalyseerd wordt, plus de twee waar hij tegen
 * afgezet wordt.
 *
 * previous() is bewust niet "vorige maand" maar dezelfde lengte direct
 * ervoor: alleen dan is het verschil tussen twee periodes een verschil in
 * verkoop en niet in het aantal dagen.
 */
final class AnalysisPeriod
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
        if ($this->end->lessThan($this->start)) {
            throw new InvalidArgumentException('De einddatum ligt voor de begindatum.');
        }
    }

    public static function make(string|CarbonInterface $start, string|CarbonInterface $end): self
    {
        return new self(
            CarbonImmutable::parse($start)->startOfDay(),
            CarbonImmutable::parse($end)->endOfDay(),
        );
    }

    /** Aantal dagen, beide einden meegeteld. */
    public function days(): int
    {
        return $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;
    }

    public function previous(): self
    {
        $end = $this->start->subDay();

        return self::make($end->subDays($this->days() - 1), $end);
    }

    public function lastYear(): self
    {
        // subYearNoOverflow: 2024-02-29 wordt 2023-02-28 en niet 2023-03-01.
        return self::make($this->start->subYearNoOverflow(), $this->end->subYearNoOverflow());
    }

    public function cacheKey(): string
    {
        return $this->start->toDateString() . '_' . $this->end->toDateString();
    }
}
