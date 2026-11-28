<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts;

use UnitEnum;
use BackedEnum;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Pages\ListOpenOrderProducts;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables\OpenOrderProductsTable;

class OpenOrderProductResource extends Resource
{
    use \Dashed\DashedCore\Filament\Concerns\TranslatesResourceLabels;

    protected static ?string $model = OrderProduct::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|UnitEnum|null $navigationGroup = 'E-commerce';

    protected static ?string $label = 'Openstaande bestelling';

    protected static ?string $pluralLabel = 'Openstaande bestellingen';

    public static function getNavigationLabel(): string
    {
        return 'Openstaande bestellingen';
    }

    /**
     * Fulfillmentstatussen die als "openstaand" tellen, gelijk aan de mobiele
     * API (OpenOrderProductController::OPEN_STATUSES).
     */
    public const OPEN_FULFILLMENT_STATUSES = ['unhandled', 'in_treatment'];

    /** Tabbladen die regels samenvoegen tot één rij per product of productgroep. */
    public const GROUPED_TABS = ['grouped', 'grouped_product_group'];

    /**
     * Groepssleutel per product: het catalogus-product, of voor losse regels
     * zonder product de combinatie SKU + naam. Niet op (product_id, sku): dan
     * viel één product na een SKU-wijziging uiteen in meerdere rijen.
     */
    public const PRODUCT_KEY_SQL = "CASE WHEN dashed__order_products.product_id IS NOT NULL AND dashed__order_products.product_id <> 0 THEN CONCAT('p:', dashed__order_products.product_id) ELSE CONCAT('s:', COALESCE(dashed__order_products.sku, ''), '|', COALESCE(dashed__order_products.name, '')) END";

    /** Groepssleutel per productgroep (0 = geen productgroep), als gecorreleerde subquery. */
    public const PRODUCT_GROUP_KEY_SQL = 'COALESCE((select group_key_products.product_group_id from dashed__products as group_key_products where group_key_products.id = dashed__order_products.product_id limit 1), 0)';

    /**
     * Menu-teller: totaal aantal openstaande producten (som van de aantallen),
     * zonder regels die al klaar zijn voor productie.
     */
    public static function getNavigationBadge(): ?string
    {
        $quantity = (int) static::getEloquentQuery()
            ->whereNull('dashed__order_products.production_ready_at')
            ->sum('dashed__order_products.quantity');

        return $quantity > 0 ? (string) $quantity : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return OpenOrderProductsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        // Standaard scope: alleen orderproducten van openstaande bestellingen
        // (niet afgehandeld of in behandeling, zoals de mobiele API), alleen
        // betaalde orders (Order::PAID_STATUSES, ook zoals de API; pending en
        // geannuleerd vallen weg), en zonder shipping/payment cost regels.
        // SKU is gekwalificeerd zodat deze scope ook werkt wanneer een tab
        // de query joint met dashed__products (welke ook een sku-kolom heeft).
        return parent::getEloquentQuery()
            ->whereHas('order', fn ($q) => $q->whereIn('fulfillment_status', static::OPEN_FULFILLMENT_STATUSES)->whereIn('status', Order::PAID_STATUSES)->withRealInvoice())
            ->where(function ($q) {
                $q->whereNull('dashed__order_products.sku')
                    ->orWhereNotIn('dashed__order_products.sku', ['shipping_costs', 'payment_costs']);
            })
            ->with(['order', 'product', 'productionReadyBy']);
    }

    /**
     * Of de order van een regel prioriteit heeft (0/1), als SQL op
     * dashed__order_products. Een gecorreleerde subquery i.p.v. een join,
     * zodat de sorteringen met eigen joins en de groeperingstabs blijven werken.
     */
    public const PRIORITY_SQL = 'COALESCE((select dashed__orders.is_priority from dashed__orders where dashed__orders.id = dashed__order_products.order_id limit 1), 0)';

    /**
     * Prioriteit-orders altijd bovenaan, vóór elke sortering die Filament
     * daarna toevoegt (zoals de mobiele API ook doet).
     */
    public static function priorityFirst(Builder $query): Builder
    {
        return $query->orderByRaw(static::PRIORITY_SQL . ' desc');
    }

    /**
     * Voegt de (al gefilterde en doorzochte) orderregels samen tot één rij per
     * product ('grouped') of per productgroep ('grouped_product_group').
     *
     * Het aantal is de som van de quantity van de openstaande regels die nog
     * niet klaar zijn voor productie. Filters en zoeken horen dus vóór deze
     * stap op de regels te zitten, anders tellen ze niet mee in de som. De
     * joins (product, productgroep) zijn 1-op-1 en vermenigvuldigen niets.
     *
     * Kolommen die de tabel nodig heeft blijven bestaan: id = MIN(id) dient als
     * rijsleutel, group_key wijst terug naar de regels (zie linesInGroups()).
     */
    public static function groupLines(Builder $lines, string $mode): Builder
    {
        // applyScopes(): zonder dit valt de SoftDeletes-scope weg en tellen
        // verwijderde regels mee.
        $inner = (clone $lines)->applyScopes()->getQuery();
        $inner->orders = null;
        $inner->whereNull('dashed__order_products.production_ready_at');

        $common = [
            DB::raw('MIN(dashed__order_products.id) as id'),
            DB::raw('MIN(dashed__order_products.order_id) as order_id'),
            DB::raw('SUM(dashed__order_products.quantity) as quantity'),
            DB::raw('COUNT(DISTINCT dashed__order_products.order_id) as order_count'),
            DB::raw('NULL as deleted_at'),
            DB::raw('NULL as production_ready_at'),
            DB::raw('NULL as production_ready_by'),
            DB::raw('MIN(dashed__order_products.created_at) as created_at'),
            DB::raw('MAX(dashed__order_products.updated_at) as updated_at'),
            DB::raw('MAX(' . static::PRIORITY_SQL . ') as has_priority'),
        ];

        if ($mode === 'grouped_product_group') {
            // Lege/null product_group_ids komen samen onder 0 ("Geen
            // productgroep"). De naam komt uit de translatable kolom (huidige
            // locale, dan 'nl', dan de ruwe waarde); de locale wordt gewhitelist
            // om SQL-injectie via een gemanipuleerde locale uit te sluiten.
            $locale = preg_replace('/[^a-zA-Z]/', '', (string) app()->getLocale()) ?: 'nl';

            $inner->leftJoin('dashed__products as grouped_products', 'grouped_products.id', '=', 'dashed__order_products.product_id')
                ->leftJoin('dashed__product_groups as grouped_groups', 'grouped_groups.id', '=', 'grouped_products.product_group_id');

            $key = 'COALESCE(grouped_products.product_group_id, 0)';

            $inner->select(array_merge($common, [
                // In MIN(): MariaDB (productie) weigert onder ONLY_FULL_GROUP_BY
                // een kale expressie in de select, ook als die exact in de
                // GROUP BY staat. Per groep is de waarde gelijk, MIN() verandert niets.
                DB::raw('MIN(' . $key . ') as group_key'),
                // De tabel en de foto lezen de productgroep-id uit product_id.
                DB::raw('MIN(' . $key . ') as product_id'),
                DB::raw("'' as sku"),
                DB::raw(
                    "MIN(COALESCE(
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(grouped_groups.name, '$.\"" . $locale . "\"')), 'null'),
                        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(grouped_groups.name, '$.\"nl\"')), 'null'),
                        grouped_groups.name,
                        'Geen productgroep'
                    )) as name"
                ),
            ]))->groupBy(DB::raw($key));
        } else {
            $inner->select(array_merge($common, [
                // In MIN(): zie hierboven (MariaDB en ONLY_FULL_GROUP_BY).
                DB::raw('MIN(' . static::PRODUCT_KEY_SQL . ') as group_key'),
                DB::raw('MIN(dashed__order_products.product_id) as product_id'),
                DB::raw('MIN(dashed__order_products.sku) as sku'),
                DB::raw('MIN(dashed__order_products.name) as name'),
            ]))->groupBy(DB::raw(static::PRODUCT_KEY_SQL));
        }

        return OrderProduct::query()
            ->fromSub($inner, 'dashed__order_products')
            ->orderByDesc('dashed__order_products.has_priority');
    }

    /**
     * De regels achter gegroepeerde rijen: alle niet-klare regels uit $lines
     * (al gefilterd/doorzocht) waarvan de groepssleutel in $groupKeys zit.
     *
     * @param  array<int, int|string>  $groupKeys
     */
    public static function linesInGroups(Builder $lines, string $mode, array $groupKeys): Builder
    {
        $keySql = $mode === 'grouped_product_group' ? static::PRODUCT_GROUP_KEY_SQL : static::PRODUCT_KEY_SQL;

        if ($groupKeys === []) {
            return $lines->whereRaw('1 = 0');
        }

        return $lines
            ->whereNull('dashed__order_products.production_ready_at')
            ->whereIn(DB::raw($keySql), array_values($groupKeys));
    }

    /**
     * Extra bulkacties die een app (bv. Lovora) aan deze tabel hangt, via
     * ecommerce()->builder('openOrderProductBulkActions', ['key' => fn () => BulkAction::make(...)]).
     *
     * @return array<int, \Filament\Actions\BulkAction|\Filament\Actions\ActionGroup>
     */
    public static function extraBulkActions(): array
    {
        return array_values(array_map(
            fn ($makeAction) => $makeAction(),
            ecommerce()->builder('openOrderProductBulkActions'),
        ));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOpenOrderProducts::route('/'),
        ];
    }
}
