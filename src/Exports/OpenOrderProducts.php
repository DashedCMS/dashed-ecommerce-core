<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;

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

    private function groupedByProductGroupRows(): array
    {
        $base = OpenOrderProductResource::getEloquentQuery()->getQuery();
        $base->orders = null;
        $base->leftJoin(
            'dashed__products',
            'dashed__products.id',
            '=',
            'dashed__order_products.product_id'
        );
        $base->select([
            DB::raw('dashed__products.product_group_id as product_group_id'),
            DB::raw('SUM(dashed__order_products.quantity) as quantity'),
        ])->groupBy('dashed__products.product_group_id');

        $rows = collect($base->get());
        $groupIds = $rows->pluck('product_group_id')->filter()->all();
        $groupNames = ProductGroup::query()
            ->whereIn('id', $groupIds)
            ->get(['id', 'name'])
            ->mapWithKeys(fn ($g) => [$g->id => $g->name])
            ->all();

        return $rows
            ->map(fn ($r) => [
                $r->product_group_id ?? '',
                $r->product_group_id ? ($groupNames[$r->product_group_id] ?? '#'.$r->product_group_id) : 'Geen productgroep',
                (int) $r->quantity,
            ])
            ->toArray();
    }
}
