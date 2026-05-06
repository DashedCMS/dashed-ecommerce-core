<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Tabs\Tab;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelType;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Exports\OpenOrderProducts;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;

class ListOpenOrderProducts extends ListRecords
{
    protected static string $resource = OpenOrderProductResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Per orderregel'),
            'grouped' => Tab::make('Gegroepeerd per product')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    // Subquery die de grouped MIN/SUM resultaten levert.
                    // Filament voegt later automatisch een `id desc` tiebreaker
                    // toe op de outer query, daarom moeten we id, order_id,
                    // deleted_at, created_at en updated_at meeselecteren zodat
                    // de outer scopes (SoftDeletes) en eager-loads blijven werken.
                    $inner = (clone $query)->getQuery();
                    $inner->orders = null;
                    $inner->select([
                        DB::raw('MIN(dashed__order_products.id) as id'),
                        DB::raw('MIN(dashed__order_products.order_id) as order_id'),
                        'dashed__order_products.product_id',
                        'dashed__order_products.sku',
                        DB::raw('MIN(dashed__order_products.name) as name'),
                        DB::raw('SUM(dashed__order_products.quantity) as quantity'),
                        DB::raw('MIN(dashed__order_products.deleted_at) as deleted_at'),
                        DB::raw('MIN(dashed__order_products.created_at) as created_at'),
                        DB::raw('MAX(dashed__order_products.updated_at) as updated_at'),
                    ])->groupBy('dashed__order_products.product_id', 'dashed__order_products.sku');

                    return OrderProduct::query()
                        ->fromSub($inner, 'dashed__order_products')
                        ->with(['order', 'product']);
                }),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Exporteer Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $grouped = ($this->activeTab ?? 'all') === 'grouped';
                    $base = 'Openstaande bestellingen - '
                        . (Customsetting::get('site_name') ?: 'Webshop')
                        . ' - ' . now()->format('Y-m-d');
                    $name = $grouped ? $base . ' (gegroepeerd)' : $base;

                    return Excel::download(
                        new OpenOrderProducts($grouped),
                        $name . '.xlsx',
                        ExcelType::XLSX,
                    );
                }),

            Action::make('exportCsv')
                ->label('Exporteer CSV')
                ->icon('heroicon-o-document-text')
                ->action(function () {
                    $grouped = ($this->activeTab ?? 'all') === 'grouped';
                    $base = 'Openstaande bestellingen - '
                        . (Customsetting::get('site_name') ?: 'Webshop')
                        . ' - ' . now()->format('Y-m-d');
                    $name = $grouped ? $base . ' (gegroepeerd)' : $base;

                    return Excel::download(
                        new OpenOrderProducts($grouped),
                        $name . '.csv',
                        ExcelType::CSV,
                    );
                }),
        ];
    }
}
