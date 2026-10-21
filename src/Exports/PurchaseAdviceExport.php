<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class PurchaseAdviceExport implements FromArray
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  uit PurchaseAdvice::rows()
     */
    public function __construct(protected array $rows)
    {
    }

    public function array(): array
    {
        $header = [__('Product ID'), __('Product'), __('SKU'), __('Voorraad'), __('Verkocht'), __('Per dag'), __('Dagen tot op'), __('Advies')];

        return array_merge([$header], array_map(fn (array $row) => [
            $row['id'],
            $row['name'],
            $row['sku'],
            $row['stock'],
            $row['sold'],
            $row['per_day'],
            $row['days_left'],
            $row['advice'],
        ], $this->rows));
    }
}
