<?php

namespace Dashed\DashedEcommerceCore\Events\Quotes;

use Dashed\DashedEcommerceCore\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * De offerte is verlopen (ExpireQuotesCommand).
 */
class QuoteExpired
{
    use Dispatchable;

    public function __construct(public Quote $quote)
    {
    }
}
