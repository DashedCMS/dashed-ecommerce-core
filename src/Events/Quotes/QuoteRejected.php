<?php

namespace Dashed\DashedEcommerceCore\Events\Quotes;

use Dashed\DashedEcommerceCore\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * De klant (of een beheerder namens hem) heeft de offerte afgewezen.
 * dashed-mobile-api luistert hierop op klassenaam.
 */
class QuoteRejected
{
    use Dispatchable;

    public function __construct(public Quote $quote, public bool $notifyCustomer = true)
    {
    }
}
