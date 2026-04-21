<?php

namespace Dashed\DashedEcommerceCore\Services\AbandonedCart;

use Illuminate\Support\Collection;

interface AbandonedCartSource
{
    public function email(): ?string;

    public function siteId(): ?int;

    public function locale(): ?string;

    /**
     * Each element: ['name' => string, 'quantity' => int, 'price' => int (cents),
     *                'image' => ?string, 'product_url' => ?string].
     */
    public function items(): Collection;

    public function total(): int;

    public function currency(): string;

    public function resumeUrl(): string;

    /**
     * Extra template variables, e.g. [':orderId:' => '1234'].
     *
     * @return array<string, string>
     */
    public function variables(): array;
}
