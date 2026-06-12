<?php

namespace Dashed\DashedEcommerceCore\Contracts;

use Dashed\DashedEcommerceCore\Models\Order;

interface ShippingLabelProvider
{
    public function key(): string;

    public function label(): string;

    /**
     * Genormaliseerde rijen van labels met een fout op de actieve site.
     *
     * @return array<int, array{id:int, order_id:int|null, invoice_id:string|null, error:string}>
     */
    public function failedOrders(): array;

    public function retry(int $id): void;

    /**
     * Werkt de labelstatussen van deze order bij door de live bezorgstatus bij
     * de vervoerder op te halen. Geeft het aantal gewijzigde labels terug.
     */
    public function syncOrderStatuses(Order $order): int;

    /**
     * Heeft deze order minstens één verzendlabel bij deze provider?
     */
    public function hasLabelsForOrder(Order $order): bool;
}
