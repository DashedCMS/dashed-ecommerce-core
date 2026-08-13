<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

final class AnalysisContext
{
    public function __construct(
        public readonly AnalysisPeriod $period,
        public readonly AnalysisPeriod $previous,
        public readonly AnalysisPeriod $lastYear,
        public readonly string $siteId,
    ) {
    }

    public static function for(AnalysisPeriod $period, string $siteId): self
    {
        return new self($period, $period->previous(), $period->lastYear(), $siteId);
    }
}
