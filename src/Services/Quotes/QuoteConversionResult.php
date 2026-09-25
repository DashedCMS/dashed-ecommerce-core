<?php

namespace Dashed\DashedEcommerceCore\Services\Quotes;

use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Uitkomst van QuoteConverter::convert(): de order (als die er is), waar de
 * klant heen moet om te betalen, en de reden van een weigering.
 */
final class QuoteConversionResult
{
    public function __construct(
        public readonly ?Order $order = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $refusal = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->order !== null && $this->refusal === null;
    }
}
