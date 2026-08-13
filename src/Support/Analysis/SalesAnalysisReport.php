<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

final class SalesAnalysisReport
{
    /**
     * @param  array<string, AnalysisResult>  $results
     * @param  array<string, string>  $failed  sleutel => label van analyses die klapten
     */
    public function __construct(
        public readonly array $results,
        public readonly array $failed,
    ) {
    }

    public function resultFor(string $key): ?AnalysisResult
    {
        return $this->results[$key] ?? null;
    }

    /**
     * Alle signalen uit alle analyses, zwaarste eerst. Binnen dezelfde ernst
     * blijft de volgorde van de analyses staan, zodat de uitvoer stabiel is.
     *
     * @return array<int, Signal>
     */
    public function signals(): array
    {
        $signals = [];

        foreach ($this->results as $result) {
            foreach ($result->signals as $signal) {
                $signals[] = $signal;
            }
        }

        usort($signals, fn (Signal $a, Signal $b) => Signal::weight($b->severity) <=> Signal::weight($a->severity));

        return $signals;
    }
}
