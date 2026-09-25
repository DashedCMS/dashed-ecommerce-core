<?php

namespace Dashed\DashedEcommerceCore\Events\Quotes;

use Dashed\DashedEcommerceCore\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * De klant (of een beheerder namens hem) heeft akkoord gegeven. Er is nog
 * geen order: die komt via QuoteConverter. dashed-mobile-api luistert hierop
 * op klassenaam voor de app-push, dus hernoem deze klasse niet.
 */
class QuoteAccepted
{
    use Dispatchable;

    public function __construct(public Quote $quote, public bool $notifyCustomer = true)
    {
    }
}
