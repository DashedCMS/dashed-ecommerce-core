<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis;

/**
 * Eén bevinding uit een analyse: wat is er aan de hand, hoe erg is het, en
 * met welke cijfers onderbouwd.
 *
 * Signalen zijn het enige dat de AI-verteller te zien krijgt. Daardoor is
 * elk getal in het rapport door code berekend en hoeft het model niet te
 * rekenen.
 */
final class Signal
{
    public const URGENT = 'urgent';
    public const ATTENTION = 'aandacht';
    public const OPPORTUNITY = 'kans';

    /**
     * @param  array<string, string|int|float>  $numbers  de cijfers eronder, label => waarde
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $title,
        public readonly string $explanation,
        public readonly array $numbers = [],
        public readonly ?string $url = null,
    ) {
    }

    public static function weight(string $severity): int
    {
        return match ($severity) {
            self::URGENT => 3,
            self::ATTENTION => 2,
            self::OPPORTUNITY => 1,
            default => 0,
        };
    }
}
