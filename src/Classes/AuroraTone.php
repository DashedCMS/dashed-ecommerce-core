<?php

namespace Dashed\DashedEcommerceCore\Classes;

/**
 * Vertaalt de Filament-kleurnamen die statussen al dragen (orderStatus(),
 * fulfillmentStatus(), badges) naar de vaste toonnamen van de
 * Aurora-componenten in dashed-core. Alleen presentatie: de statussen en
 * hun kleur blijven op het model bepaald.
 */
class AuroraTone
{
    public const TONES = ['mint', 'blue', 'purple', 'amber', 'red', 'neutral'];

    public static function from(?string $color): string
    {
        return match ($color) {
            'success' => 'mint',
            'primary', 'info' => 'blue',
            'purple' => 'purple',
            'warning' => 'amber',
            'danger' => 'red',
            default => in_array($color, self::TONES, true) ? $color : 'neutral',
        };
    }
}
