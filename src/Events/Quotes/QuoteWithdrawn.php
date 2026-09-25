<?php

namespace Dashed\DashedEcommerceCore\Events\Quotes;

use Dashed\DashedEcommerceCore\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * De beheerder heeft de offerte ingetrokken.
 */
class QuoteWithdrawn
{
    use Dispatchable;

    public function __construct(public Quote $quote)
    {
    }
}
