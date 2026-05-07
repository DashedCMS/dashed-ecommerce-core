<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
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
            'grouped_product_group' => Tab::make('Gegroepeerd per productgroep')
                ->modifyQueryUsing(function (Builder $query): Builder {
                    // Subquery die SUM(quantity) per product_group_id levert
                    // door order_products -> products te joinen. Lege/null
                    // product_group_ids komen samen onder een 'losse'-bucket
                    // (NULL). De Productnaam-kolom toont de product_group naam
                    // via JSON_UNQUOTE op de translatable kolom (huidige
                    // locale, met fallback naar 'nl' en daarna de raw JSON).
                    // Locale wordt gewhitelist op alfanumeriek om SQL-
                    // injectie via een gemanipuleerde locale uit te sluiten.
                    $locale = preg_replace('/[^a-zA-Z]/', '', (string) app()->getLocale()) ?: 'nl';

                    $inner = (clone $query)->getQuery();
                    $inner->orders = null;
                    $inner->leftJoin(
                        'dashed__products',
                        'dashed__products.id',
                        '=',
                        'dashed__order_products.product_id'
                    );
                    $inner->leftJoin(
                        'dashed__product_groups',
                        'dashed__product_groups.id',
                        '=',
                        'dashed__products.product_group_id'
                    );
                    $inner->select([
                        DB::raw('MIN(dashed__order_products.id) as id'),
                        DB::raw('MIN(dashed__order_products.order_id) as order_id'),
                        DB::raw('dashed__products.product_group_id as product_id'),
                        DB::raw("'' as sku"),
                        DB::raw(
                            "COALESCE(
                                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(dashed__product_groups.name, '$.\"".$locale."\"')), 'null'),
                                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(dashed__product_groups.name, '$.\"nl\"')), 'null'),
                                dashed__product_groups.name,
                                'Geen productgroep'
                            ) as name"
                        ),
                        DB::raw('SUM(dashed__order_products.quantity) as quantity'),
                        DB::raw('MIN(dashed__order_products.deleted_at) as deleted_at'),
                        DB::raw('MIN(dashed__order_products.created_at) as created_at'),
                        DB::raw('MAX(dashed__order_products.updated_at) as updated_at'),
                    ])->groupBy('dashed__products.product_group_id', 'dashed__product_groups.name');

                    return OrderProduct::query()
                        ->fromSub($inner, 'dashed__order_products')
                        ->with(['order']);
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
                    $mode = $this->resolveExportMode();
                    $base = 'Openstaande bestellingen - '
                        . (Customsetting::get('site_name') ?: 'Webshop')
                        . ' - ' . now()->format('Y-m-d');
                    $suffix = match ($mode) {
                        'grouped' => ' (gegroepeerd)',
                        'grouped_product_group' => ' (gegroepeerd per productgroep)',
                        default => '',
                    };

                    return Excel::download(
                        new OpenOrderProducts($mode),
                        $base . $suffix . '.csv',
                        ExcelType::CSV,
                    );
                }),
        ];
    }

    private function resolveExportMode(): string
    {
        return match ($this->activeTab ?? 'all') {
            'grouped' => 'grouped',
            'grouped_product_group' => 'grouped_product_group',
            default => 'all',
        };
    }
}
