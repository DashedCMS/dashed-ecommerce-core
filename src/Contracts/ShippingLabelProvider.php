<?php

namespace Dashed\DashedEcommerceCore\Contracts;

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
}
