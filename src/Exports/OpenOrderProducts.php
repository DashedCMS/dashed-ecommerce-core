<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;

class OpenOrderProducts implements FromArray
{
    /**
     * @param  string|bool  $mode  Backwards-compatible: legacy bool treats true as 'grouped',
     *                             false as 'all'. New string values: 'all', 'grouped',
     *                             'grouped_product_group'.
     */
    public function __construct(public string|bool $mode = 'all')
    {
        if (is_bool($mode)) {
            $this->mode = $mode ? 'grouped' : 'all';
        }
    }

    public function array(): array
    {
        $rows = match ($this->mode) {
            'grouped' => $this->groupedRows(),
            'grouped_product_group' => $this->groupedByProductGroupRows(),
            default => $this->detailedRows(),
        };

        $header = match ($this->mode) {
            'grouped' => ['Product ID', 'Productnaam', 'SKU', 'Totaal aantal'],
            'grouped_product_group' => ['Productgroep ID', 'Productgroep', 'Totaal aantal'],
            default => ['Bestelling', 'Product ID', 'Productnaam', 'SKU', 'Aantal', 'Order origin', 'Klant', 'Besteld op'],
        };

        return array_merge([$header], $rows);
    }

    private function detailedRows(): array
    {
        return OpenOrderProductResource::priorityFirst(OpenOrderProductResource::getEloquentQuery())
            ->with(['order', 'product'])
            ->orderByDesc('order_id')
            ->get()
            ->map(fn ($op) => [
                $op->order?->invoice_id,
                $op->product_id,
                $op->name,
                $op->sku,
                (int) $op->quantity,
                $op->order?->order_origin ? ucfirst($op->order->order_origin) : '-',
                $op->order?->name,
                optional($op->order?->created_at)->format('d-m-Y H:i'),
            ])
            ->toArray();
    }

    private function groupedRows(): array
    {
        // Zelfde samenvoeging als de tab (OpenOrderProductResource::groupLines):
        // per product, som van de openstaande, nog niet klare regels.
        return OpenOrderProductResource::groupLines(OpenOrderProductResource::getEloquentQuery(), 'grouped')
            ->get()
            ->map(fn ($r) => [
                $r->product_id,
                $r->name,
                $r->sku,
                (int) $r->quantity,
            ])
            ->toArray();
    }

    private function groupedByProductGroupRows(): array
    {
        return OpenOrderProductResource::groupLines(OpenOrderProductResource::getEloquentQuery(), 'grouped_product_group')
            ->get()
            ->map(fn ($r) => [
                $r->product_id ?: '',
                $r->name,
                (int) $r->quantity,
            ])
            ->toArray();
    }
}
