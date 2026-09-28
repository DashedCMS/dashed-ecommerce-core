<?php

namespace Dashed\DashedEcommerceCore\Events\Quotes;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Quote;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Een geaccepteerde offerte is via QuoteConverter omgezet naar een
 * bestelling. Vuurt alleen bij de omzetting die de order echt aanmaakte, niet
 * bij een herhaalde aanroep die de bestaande order teruggeeft, en ook niet
 * bij een geweigerde poging (bijvoorbeeld een op-rekening-verzoek dat de
 * kredietcontrole niet doorstaat): de order blijft dan concept en ongeplaatst.
 */
class QuoteConverted
{
    use Dispatchable;

    public function __construct(public Quote $quote, public Order $order, public string $mode)
    {
    }
}
