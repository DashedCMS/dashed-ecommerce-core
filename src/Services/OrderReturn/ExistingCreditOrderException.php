<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use InvalidArgumentException;
use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * De bestelling van een retour heeft al een of meer creditorders (van een
 * eerdere retour, de annuleerknop of een orderwijziging). Een nieuwe
 * creditorder komt er dan alleen na een expliciete bevestiging, anders
 * wordt hetzelfde geld makkelijk twee keer gecrediteerd.
 *
 * Erft van InvalidArgumentException, zodat elke aanroeper die een
 * weigering van de processor al netjes afhandelt dit ook doet.
 */
class ExistingCreditOrderException extends InvalidArgumentException
{
    /** @param  Collection<int, Order>  $creditOrders */
    public function __construct(public readonly Collection $creditOrders)
    {
        parent::__construct(__('Deze bestelling heeft al een creditorder (:nummers). Bevestig dat er nog een bij moet komen.', [
            'nummers' => $creditOrders->pluck('invoice_id')->filter()->implode(', ') ?: '#' . $creditOrders->pluck('id')->implode(', #'),
        ]));
    }
}
