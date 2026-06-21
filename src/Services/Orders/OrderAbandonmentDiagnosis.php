<?php

namespace Dashed\DashedEcommerceCore\Services\Orders;

/**
 * Uitkomst van de OrderAbandonmentAnalyzer: de waarschijnlijke reden dat een
 * bestelling niet (volledig) is afgerekend, met onderbouwend bewijs.
 */
class OrderAbandonmentDiagnosis
{
    /**
     * @param  string  $cause  machinekey, bijv. 'payment_start_failed'
     * @param  string  $label  leesbare NL-omschrijving
     * @param  string  $confidence  'high' | 'medium' | 'low'
     * @param  array<int, string>  $evidence  onderbouwende regels
     */
    public function __construct(
        public string $cause,
        public string $label,
        public string $confidence,
        public array $evidence = [],
    ) {
    }
}
