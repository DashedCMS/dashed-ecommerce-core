<?php

namespace Dashed\DashedEcommerceCore\Classes;

/**
 * De genormaliseerde verzendlabel-statussen zoals de carrier-syncs (MyParcel,
 * Veloyd) ze opslaan, met hun Nederlandse weergave. Eén bron, zodat de
 * status-badges in de API en de orderlogs dezelfde woorden gebruiken.
 */
class ShipmentStatus
{
    /** @return array<string, array{0: string, 1: string}> key => [label, tone] */
    public static function meta(): array
    {
        return [
            'concept' => ['Concept', 'neutral'],
            'printed' => ['Geprint', 'neutral'],
            'shipped' => ['Verzonden', 'success'],
            'in_transit' => ['Onderweg', 'warning'],
            'pickup' => ['Klaar voor afhalen', 'warning'],
            'delivered' => ['Geleverd', 'success'],
            'returned' => ['Retour', 'warning'],
            'cancelled' => ['Geannuleerd', 'danger'],
            'error' => ['Fout', 'danger'],
        ];
    }

    public static function name(?string $key): string
    {
        return self::meta()[(string) $key][0] ?? 'Onbekend';
    }
}
