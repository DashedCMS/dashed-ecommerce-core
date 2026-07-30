<?php

namespace Dashed\DashedEcommerceCore\Mail\Concerns;

use Dashed\DashedEcommerceCore\Models\OrderReturn;

/**
 * Zorgt dat een beheerder die op een retour-notificatie reageert bij de klant
 * uitkomt en niet bij het eigen afzenderadres van de shop.
 */
trait RepliesToCustomer
{
    /**
     * @return array{0: string, 1: ?string}|null [email, naam]
     */
    protected function customerReplyTo(OrderReturn $orderReturn): ?array
    {
        $order = $orderReturn->order;

        $email = trim((string) ($order?->email ?: $orderReturn->email));

        if ($email === '') {
            return null;
        }

        $name = trim(($order?->first_name ?? '') . ' ' . ($order?->last_name ?? ''));

        return [$email, $name !== '' ? $name : null];
    }
}
