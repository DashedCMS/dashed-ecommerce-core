<?php

namespace Dashed\DashedEcommerceCore\Services\Gs1;

/**
 * Wat een run zou doen: welke code naar welk product gaat, welke producten
 * een plaatshouder krijgen omdat de pool op is, en welke producten
 * wachten op ontbrekende velden.
 */
final class Gs1Plan
{
    /**
     * @param  list<array{line_id: int, product_id: int, gtin: string, reused: bool}>  $assignments
     * @param  list<int>  $placeholders
     * @param  array<int, list<string>>  $blocked
     */
    public function __construct(
        public readonly array $assignments,
        public readonly array $placeholders,
        public readonly array $blocked,
    ) {
    }
}
