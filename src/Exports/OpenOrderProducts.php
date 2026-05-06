<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;

class OpenOrderProducts implements FromArray
{
    public function __construct(public bool $grouped = false)
    {
    }

    public function array(): array
    {
        $rows = $this->grouped ? $this->groupedRows() : $this->detailedRows();

        $header = $this->grouped
            ? ['Product ID', 'Productnaam', 'SKU', 'Totaal aantal']
            : ['Bestelling', 'Product ID', 'Productnaam', 'SKU', 'Aantal', 'Order origin', 'Klant', 'Besteld op'];

        return array_merge([$header], $rows);
    }

    private function detailedRows(): array
    {
        return OpenOrderProductResource::getEloquentQuery()
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
        // Spiegelt de tab-grouping query, maar wordt direct uitgevoerd zodat de
        // export niet afhankelijk is van table state.
        $base = OpenOrderProductResource::getEloquentQuery()->getQuery();
        $base->orders = null;
        $base->select([
            DB::raw('MIN(dashed__order_products.product_id) as product_id'),
            'dashed__order_products.sku',
            DB::raw('MIN(dashed__order_products.name) as name'),
            DB::raw('SUM(dashed__order_products.quantity) as quantity'),
        ])->groupBy('dashed__order_products.product_id', 'dashed__order_products.sku');

        return collect($base->get())
            ->map(fn ($r) => [
                $r->product_id,
                $r->name,
                $r->sku,
                (int) $r->quantity,
            ])
            ->toArray();
    }
}
