<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Dashed\DashedEcommerceCore\Models\Order;

class OrderLookupService
{
    /**
     * Statussen die in aanmerking komen voor herroeping.
     */
    public const ELIGIBLE_STATUSES = ['paid', 'partially_paid', 'waiting_for_confirmation'];

    public function find(string $orderNumber, string $email): ?Order
    {
        $orderNumber = trim($orderNumber);
        $email = trim($email);

        if ($orderNumber === '' || $email === '') {
            return null;
        }

        return Order::query()
            ->whereIn('status', self::ELIGIBLE_STATUSES)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->where(function ($query) use ($orderNumber) {
                $query->where('invoice_id', $orderNumber);
                if (is_numeric($orderNumber)) {
                    $query->orWhere('id', (int) $orderNumber);
                }
            })
            ->first();
    }
}
