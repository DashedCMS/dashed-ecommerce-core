<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables;

use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Illuminate\Support\Collection;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Support\SmartSearch;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\OpenOrderProductResource;

class OpenOrderProductsTable
{
    /** Deadline van de order van een regel (leverdatum leidend), als gecorreleerde subquery. */
    public const DEADLINE_SQL = '(select COALESCE(deadline_orders.deliver_by_date, deadline_orders.ship_by_date) from dashed__orders as deadline_orders where deadline_orders.id = dashed__order_products.order_id limit 1)';

    /** Sorteer op deadline; regels zonder deadline altijd onderaan. */
    public static function orderByDeadline(Builder $query, string $direction): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw(static::DEADLINE_SQL . ' is null')
            ->orderByRaw(static::DEADLINE_SQL . ' ' . $direction);
    }

    /**
     * 1 als het product van de regel nu op voorraad is, anders 0. Met
     * voorraadbeheer telt een voorraad van 0 of meer: de voorraad is bij het
     * bestellen al afgeboekt, dus 0 betekent dat alle openstaande bestellingen
     * gedekt zijn. Zonder voorraadbeheer telt de handmatige voorraadstatus.
     */
    public const STOCK_NOW_SQL = "(select case when stock_now_products.use_stock = 1 then stock_now_products.stock >= 0 else stock_now_products.stock_status = 'in_stock' end from dashed__products as stock_now_products where stock_now_products.id = dashed__order_products.product_id limit 1)";

    /**
     * Sorteer op voorraad bij besteldatum en daarbinnen op voorraad nu.
     * Oplopend: niet op voorraad besteld (nu nog niet, nu wel), dan op
     * voorraad besteld (nu niet, nu wel). Regels zonder status altijd onderaan.
     */
    public static function orderByStockStatus(Builder $query, string $direction): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderByRaw('dashed__order_products.stock_status_at_order is null')
            ->orderByRaw("dashed__order_products.stock_status_at_order = 'in_stock' " . $direction)
            ->orderByRaw('coalesce(' . static::STOCK_NOW_SQL . ', 0) ' . $direction);
    }

    /** Filter op deadline: late, today, tomorrow of today_or_earlier. */
    public static function filterDeadline(Builder $query, ?string $value): Builder
    {
        $today = now()->toDateString();

        return match ($value) {
            'late' => $query->whereRaw(static::DEADLINE_SQL . ' < ?', [$today]),
            'today' => $query->whereRaw(static::DEADLINE_SQL . ' = ?', [$today]),
            'tomorrow' => $query->whereRaw(static::DEADLINE_SQL . ' = ?', [now()->addDay()->toDateString()]),
            'today_or_earlier' => $query->whereRaw(static::DEADLINE_SQL . ' <= ?', [$today]),
            default => $query,
        };
    }

    /** Of de tabel nu een gegroepeerde tab toont (één rij per product/productgroep). */
    public static function isGrouped($livewire): bool
    {
        return in_array($livewire->activeTab ?? null, OpenOrderProductResource::GROUPED_TABS, true);
    }

    /**
     * Zoeken over naam, gekozen opties, SKU en factuurnummer (zoals de mobiele
     * API): elk woord moet ergens matchen. Werkt op de losse regels, dus ook in
     * de gegroepeerde tabs (die pas daarna samenvoegen).
     */
    public static function applySearch(Builder $query, ?string $search): Builder
    {
        SmartSearch::apply($query, $search, [
            'dashed__order_products.name',
            'dashed__order_products.sku',
            DB::raw(OrderProduct::extrasSearchSql('dashed__order_products.product_extras')),
            DB::raw('(select search_orders.invoice_id from dashed__orders as search_orders where search_orders.id = dashed__order_products.order_id limit 1)'),
        ]);

        return $query;
    }

    /**
     * Meldt de regels achter $records klaar (of weer niet klaar) en geeft het
     * aantal gewijzigde regels terug. In een gegroepeerde tab zijn dat alle
     * openstaande, nog niet klare regels van de gekozen producten.
     */
    public static function markLines($livewire, iterable $records, bool $ready): int
    {
        $user = auth()->user();
        $user = $user instanceof User ? $user : null;
        $changed = 0;

        foreach ($livewire->openLinesForRecords($records) as $line) {
            $changed += (int) ($ready ? $line->markProductionReady($user) : $line->markProductionNotReady($user));
        }

        Notification::make()
            ->title($ready
                ? trans_choice(':count regel klaar gemeld|:count regels klaar gemeld', $changed, ['count' => $changed])
                : trans_choice(':count regel op niet klaar gezet|:count regels op niet klaar gezet', $changed, ['count' => $changed]))
            ->success()
            ->send();

        return $changed;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->getStateUsing(function ($record, $livewire) {
                        // Eén medialookup per pagina in plaats van per rij,
                        // zelfde aanpak als in ProductResource.
                        static $preloadedPage = null;
                        $currentPage = $livewire->getTablePage() ?? 1;

                        if ($preloadedPage !== $currentPage) {
                            $preloadedPage = $currentPage;

                            try {
                                $ids = $livewire->getTableRecords()
                                    ->map(fn ($row) => self::imageIdFor($row, $livewire->activeTab ?? null))
                                    ->filter()
                                    ->values()
                                    ->all();

                                if ($ids) {
                                    mediaHelper()->preloadMediaUrls($ids, 'original');
                                }
                            } catch (\Throwable $e) {
                            }
                        }

                        return self::imageUrlFor($record, $livewire->activeTab ?? null);
                    }),
                // Kolommen per order (bestelling, on hold, origin, status, datum,
                // klant, deadline) zijn in de gegroepeerde tabs verborgen: een
                // rij bundelt daar meerdere orders, dus MIN(order_id) zou een
                // willekeurige order tonen.
                TextColumn::make('order.invoice_id')
                    ->label(__('Bestelling'))
                    ->sortable()
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('product_id')
                    ->label(__('Product ID'))
                    ->toggleable()
                    ->sortable()
                    // Op de productgroep-tab staat hier de productgroep-id.
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) !== 'grouped_product_group'),
                IconColumn::make('order.is_priority')
                    ->label(__('Prioriteit'))
                    ->state(fn ($record, $livewire): bool => static::isGrouped($livewire)
                        ? (bool) $record->has_priority
                        : (bool) $record->order?->is_priority)
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),
                IconColumn::make('order.on_hold_at')
                    ->label(__('On hold'))
                    ->state(fn ($record): bool => $record->order?->on_hold_at !== null)
                    ->boolean()
                    ->trueIcon('heroicon-s-pause-circle')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn ($record): ?string => $record->order?->on_hold_at ? ($record->order->on_hold_reason ?: __('On hold: niet uitleveren')) : null)
                    ->toggleable()
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('name')
                    ->label(__('Productnaam'))
                    ->wrap()
                    ->description(function ($record) {
                        if (! is_array($record->product_extras) || ! $record->product_extras) {
                            return null;
                        }

                        return collect($record->product_extras)
                            ->map(fn ($option) => ($option['name'] ?? '') . ': ' . ($option['value'] ?? ''))
                            ->implode(' | ');
                    })
                    ->sortable(),
                TextColumn::make('sku')
                    ->label(__('SKU'))
                    ->toggleable()
                    ->sortable()
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) !== 'grouped_product_group'),
                TextColumn::make('quantity')
                    ->label(__('Aantal'))
                    // Gegroepeerd: de som van de openstaande, nog niet klare regels.
                    ->formatStateUsing(fn ($state): string => (string) (int) $state)
                    ->sortable(),
                TextColumn::make('order_count')
                    ->label(__('Bestellingen'))
                    ->formatStateUsing(fn ($state): string => (string) (int) $state)
                    ->sortable()
                    ->visible(fn ($livewire): bool => static::isGrouped($livewire)),
                TextColumn::make('production_ready_at')
                    ->label(__('Klaar'))
                    ->badge()
                    ->getStateUsing(fn ($record): ?string => $record->production_ready_at ? __('Klaar') : null)
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->description(function ($record): ?string {
                        if (! $record->production_ready_at) {
                            return null;
                        }

                        $by = $record->production_ready_by ? $record->productionReadyBy?->name : null;

                        return trim(($by ?: __('Onbekend')) . ', ' . $record->production_ready_at->format('d-m H:i'));
                    })
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable()
                    // Gegroepeerd telt alleen niet-klare regels, dus geen eigen klaar-status.
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('product.stock')
                    ->label(__('Voorraad'))
                    ->getStateUsing(function ($record) {
                        $product = $record->product;
                        if (! $product) {
                            return '-';
                        }
                        if (! $product->use_stock) {
                            return $product->stock_status === 'in_stock' ? '∞' : '0';
                        }

                        return (int) $product->stock;
                    })
                    ->badge()
                    ->color(function ($record) {
                        $product = $record->product;
                        if (! $product || ! $product->use_stock) {
                            return 'gray';
                        }
                        $stock = (int) $product->stock;
                        $needed = (int) $record->quantity;
                        if ($stock <= 0) {
                            return 'danger';
                        }
                        if ($stock < $needed) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->leftJoin(
                            'dashed__products as voorraad_join',
                            'voorraad_join.id',
                            '=',
                            'dashed__order_products.product_id'
                        )
                        ->orderBy('voorraad_join.stock', $direction)
                        ->select('dashed__order_products.*'))
                    // Op de productgroep-tab is product_id de productgroep-id,
                    // dus $record->product zou een willekeurig product zijn.
                    ->visible(fn ($livewire): bool => ($livewire->activeTab ?? null) !== 'grouped_product_group'),
                TextColumn::make('stock_status_at_order')
                    ->label(__('Voorraad bij besteldatum'))
                    ->badge()
                    ->toggleable()
                    ->getStateUsing(fn ($record): ?string => match ($record->stock_status_at_order) {
                        OrderProduct::STOCK_STATUS_IN_STOCK => __('Op voorraad'),
                        OrderProduct::STOCK_STATUS_OUT_OF_STOCK => __('Niet op voorraad'),
                        default => null,
                    })
                    ->color(fn ($record): string => match ($record->stock_status_at_order) {
                        OrderProduct::STOCK_STATUS_IN_STOCK => 'success',
                        OrderProduct::STOCK_STATUS_OUT_OF_STOCK => 'danger',
                        default => 'gray',
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => static::orderByStockStatus($query, $direction))
                    // Een gegroepeerde regel bundelt meerdere orders/orderregels,
                    // dus geen betekenisvolle per-regel voorraadstatus (net als de
                    // Deadline- en Klant-kolommen hieronder).
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('order.order_origin')
                    ->label(__('Order origin'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->leftJoin(
                            'dashed__orders as order_origin_join',
                            'order_origin_join.id',
                            '=',
                            'dashed__order_products.order_id'
                        )
                        ->orderBy('order_origin_join.order_origin', $direction)
                        ->select('dashed__order_products.*'))
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('order.fulfillment_status')
                    ->label(__('Fulfillment status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'handled' => 'success',
                        'unhandled' => 'warning',
                        'packed' => 'warning',
                        'in_treatment', 'ready_for_pickup' => 'info',
                        'shipped' => 'primary',
                        default => 'gray',
                    })
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('deadline')
                    ->label(__('Deadline'))
                    ->toggleable()
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $deadline = $record->order?->effectiveDeadline();

                        if (! $deadline) {
                            return '-';
                        }

                        return ($record->order->deadline_source === 'bol' ? 'Bol ' : '') . $deadline->format('d-m');
                    })
                    ->color(fn ($record): string => match ($record->order?->deadlineUrgency()) {
                        'late', 'today' => 'danger',
                        'tomorrow' => 'warning',
                        default => 'gray',
                    })
                    ->tooltip(fn ($record): ?string => $record->order?->deadlineLabel())
                    ->sortable(query: fn (Builder $query, string $direction) => static::orderByDeadline($query, $direction))
                    // Een gegroepeerde regel bundelt meerdere orders, dus geen eigen deadline.
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('order.name')
                    ->label(__('Klant'))
                    ->toggleable()
                    // Bij de gegroepeerde weergaven hoort geen klantnaam (een
                    // regel bundelt meerdere orders/klanten).
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
                TextColumn::make('order.created_at')
                    ->label(__('Besteld op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->visible(fn ($livewire): bool => ! static::isGrouped($livewire)),
            ])
            // Per regel: nieuwste bestelling eerst; gegroepeerd: grootste aantal eerst.
            ->defaultSort(fn (Builder $query, $livewire): Builder => $query->orderBy(
                static::isGrouped($livewire) ? 'dashed__order_products.quantity' : 'dashed__order_products.order_id',
                'desc',
            ))
            ->searchable()
            ->searchUsing(fn (Builder $query, ?string $search): Builder => static::applySearch($query, $search))
            ->filters([
                TernaryFilter::make('production_ready')
                    ->label(__('Klaar'))
                    ->placeholder(__('Alle regels'))
                    ->trueLabel(__('Alleen klaar'))
                    ->falseLabel(__('Alleen niet klaar'))
                    // Standaard alleen wat nog gemaakt moet worden. De
                    // gegroepeerde tabs tellen klaar-regels sowieso nooit mee.
                    ->default(false)
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('dashed__order_products.production_ready_at'),
                        false: fn (Builder $query) => $query->whereNull('dashed__order_products.production_ready_at'),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('deadline')
                    ->label(__('Deadline'))
                    ->options([
                        'late' => __('Te laat'),
                        'today' => __('Vandaag'),
                        'tomorrow' => __('Morgen'),
                        'today_or_earlier' => __('Vandaag of eerder'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => static::filterDeadline($query, $data['value'] ?? null)),

                SelectFilter::make('fulfillment_status')
                    ->label(__('Fulfillment status'))
                    // Alleen openstaande statussen: de basisquery laat andere niet door.
                    ->options(array_intersect_key(Orders::getFulfillmentStatusses(), array_flip(OpenOrderProductResource::OPEN_FULFILLMENT_STATUSES)))
                    ->multiple()
                    // Standaard "openstaand": nog niet afgehandeld én in behandeling.
                    ->default(OpenOrderProductResource::OPEN_FULFILLMENT_STATUSES)
                    ->query(function (Builder $query, array $data): Builder {
                        $values = array_values(array_filter((array) ($data['values'] ?? [])));
                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas('order', fn ($s) => $s->whereIn('fulfillment_status', $values));
                    }),

                SelectFilter::make('order_origin')
                    ->label(__('Order origin'))
                    ->multiple()
                    ->options(fn () => Order::query()
                        ->whereNotNull('order_origin')
                        ->where('order_origin', '!=', '')
                        ->groupBy('order_origin')
                        ->pluck('order_origin', 'order_origin')
                        ->map(fn ($v) => ucfirst((string) $v))
                        ->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }

                        return $query->whereHas('order', fn ($s) => $s->whereIn('order_origin', $data['values']));
                    }),

                TernaryFilter::make('is_priority')
                    ->label(__('Prioriteit'))
                    ->queries(
                        true: fn ($query) => $query->whereHas('order', fn ($q) => $q->where('is_priority', true)),
                        false: fn ($query) => $query->whereHas('order', fn ($q) => $q->where('is_priority', false)),
                        blank: fn ($query) => $query,
                    ),
                TernaryFilter::make('on_hold')
                    ->label(__('On hold'))
                    ->placeholder(__('Alle regels'))
                    ->trueLabel(__('Alleen on hold'))
                    ->falseLabel(__('Zonder on hold'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('order', fn (Builder $q) => $q->whereNotNull('on_hold_at')),
                        false: fn (Builder $query) => $query->whereHas('order', fn (Builder $q) => $q->whereNull('on_hold_at')),
                    ),
                TernaryFilter::make('has_product_extras')
                    ->label(__('Product opties'))
                    ->placeholder(__('Alles'))
                    ->trueLabel('Met opties')
                    ->falseLabel('Zonder opties')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('dashed__order_products.product_extras')
                            ->whereRaw("JSON_VALID(dashed__order_products.product_extras) = 1")
                            ->whereRaw("JSON_LENGTH(dashed__order_products.product_extras) > 0"),
                        false: fn (Builder $query) => $query
                            ->where(fn (Builder $q) => $q
                                ->whereNull('dashed__order_products.product_extras')
                                ->orWhereRaw("JSON_VALID(dashed__order_products.product_extras) = 1 AND JSON_LENGTH(dashed__order_products.product_extras) = 0")),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_product_id')
                    ->label(__('Product ID'))
                    ->placeholder(__('Alles'))
                    ->trueLabel('Met product ID')
                    ->falseLabel('Zonder product ID')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('dashed__order_products.product_id')
                            ->where('dashed__order_products.product_id', '!=', 0),
                        false: fn (Builder $query) => $query
                            ->where(fn (Builder $q) => $q
                                ->whereNull('dashed__order_products.product_id')
                                ->orWhere('dashed__order_products.product_id', 0)),
                        blank: fn (Builder $query) => $query,
                    ),

                SelectFilter::make('product')
                    ->label(__('Product'))
                    ->multiple()
                    ->searchable()
                    ->options(fn () => \Dashed\DashedEcommerceCore\Models\Product::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($product) => [$product->id => $product->name])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }

                        return $query->whereIn('dashed__order_products.product_id', $data['values']);
                    }),

                SelectFilter::make('product_group')
                    ->label(__('Productgroep'))
                    ->multiple()
                    ->searchable()
                    ->options(fn () => \Dashed\DashedEcommerceCore\Models\ProductGroup::query()
                        ->get()
                        ->mapWithKeys(fn ($group) => [$group->id => $group->name])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }

                        return $query->whereHas('product', fn ($q) => $q->whereIn('product_group_id', $data['values']));
                    }),

                SelectFilter::make('product_category')
                    ->label(__('Productcategorie'))
                    ->multiple()
                    ->searchable()
                    ->options(fn () => \Dashed\DashedEcommerceCore\Models\ProductCategory::all()
                        ->mapWithKeys(fn ($category) => [$category->id => $category->nameWithParents])
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['values'])) {
                            return $query;
                        }

                        return $query->whereHas('product.productCategories', fn ($q) => $q->whereIn('dashed__product_categories.id', $data['values']));
                    }),
            ])
            ->recordActions([
                Action::make('markProductionReady')
                    ->label(__('Markeer klaar'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->iconButton()
                    ->tooltip(fn ($livewire): string => static::isGrouped($livewire)
                        ? __('Alle openstaande regels van dit product klaar melden')
                        : __('Markeer klaar'))
                    ->requiresConfirmation(fn ($livewire): bool => static::isGrouped($livewire))
                    ->visible(fn ($record, $livewire): bool => static::isGrouped($livewire) || ! $record->production_ready_at)
                    ->action(fn ($record, $livewire) => static::markLines($livewire, [$record], true)),
                Action::make('markProductionNotReady')
                    ->label(__('Markeer niet klaar'))
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->iconButton()
                    ->tooltip(__('Markeer niet klaar'))
                    ->visible(fn ($record, $livewire): bool => ! static::isGrouped($livewire) && (bool) $record->production_ready_at)
                    ->action(fn ($record, $livewire) => static::markLines($livewire, [$record], false)),
            ])
            ->toolbarActions([
                BulkActionGroup::make(array_merge([
                    BulkAction::make('markProductionReady')
                        ->label(__('Markeer klaar'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records, $livewire) => static::markLines($livewire, $records, true)),
                    BulkAction::make('markProductionNotReady')
                        ->label(__('Markeer niet klaar'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        // Gegroepeerd bevat alleen niet-klare regels.
                        ->visible(fn ($livewire): bool => ! static::isGrouped($livewire))
                        ->action(fn (Collection $records, $livewire) => static::markLines($livewire, $records, false)),
                ], OpenOrderProductResource::extraBulkActions())),
            ])
            ->recordUrl(function ($record, $livewire) {
                // In de grouped tabs is order_id een MIN() over meerdere
                // orders, dus dat zou misleidend zijn — alleen linken op de
                // per-orderregel-weergave.
                if (static::isGrouped($livewire)) {
                    return null;
                }

                return $record->order_id
                    ? OrderResource::getUrl('view', ['record' => $record->order_id])
                    : null;
            }, shouldOpenInNewTab: true)
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession();
    }

    /**
     * De media-id van de foto die bij deze rij hoort, of null.
     *
     * Op het tabblad gegroepeerd per productgroep staat in de kolom product_id
     * geen product-id maar de product_group_id: de subquery in
     * ListOpenOrderProducts aliast die er zelf in. Wie daar $record->product
     * leest, krijgt het product dat toevallig dat id draagt en dus een foto van
     * een heel ander artikel. Vandaar de splitsing op het actieve tabblad.
     */
    public static function imageIdFor(OrderProduct $record, ?string $activeTab): ?string
    {
        if ($activeTab === 'grouped_product_group') {
            return $record->product_id
                ? ProductGroup::find($record->product_id)?->firstImage
                : null;
        }

        return $record->product?->firstImage;
    }

    /**
     * De URL van de foto die bij deze rij hoort, of null.
     *
     * getSingleMedia() geeft een echte medialibrary-item terug, maar laat een
     * niet-numerieke string ongewijzigd door als URL. Allebei worden hier
     * afgehandeld, zodat een handmatig ingevulde URL net zo goed werkt als een
     * media-id.
     */
    public static function imageUrlFor(OrderProduct $record, ?string $activeTab): ?string
    {
        $imageId = self::imageIdFor($record, $activeTab);

        if (! $imageId) {
            return null;
        }

        $media = mediaHelper()->getSingleMedia($imageId, 'original');

        if (is_string($media)) {
            return $media ?: null;
        }

        return $media->url ?? null;
    }
}
