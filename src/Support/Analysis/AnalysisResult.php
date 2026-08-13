<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

/**
 * Wat een analyse oplevert: feiten om te tonen en signalen om iets mee te
 * doen. De vorm van $facts is vrij per analyse, want vier kerncijfers zien
 * er anders uit dan een tabel met producten.
 */
final class AnalysisResult
{
    /**
     * @param  array<string, mixed>  $facts
     * @param  array<int, Signal>  $signals
     */
    public function __construct(
        public readonly array $facts = [],
        public readonly array $signals = [],
    ) {
    }

    public static function empty(): self
    {
        return new self();
    }
}
