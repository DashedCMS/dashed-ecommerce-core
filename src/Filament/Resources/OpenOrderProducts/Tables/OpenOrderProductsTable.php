<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

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
                TextColumn::make('order.invoice_id')
                    ->label(__('Bestelling'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product_id')
                    ->label(__('Product ID'))
                    ->toggleable()
                    ->sortable(),
                IconColumn::make('order.is_priority')
                    ->label(__('Prioriteit'))
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-minus-small')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(),
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
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $like = '%' . $search . '%';

                        return $query->where(fn (Builder $q) => $q
                            ->where('dashed__order_products.name', 'like', $like)
                            ->orWhereRaw(OrderProduct::extrasSearchSql('dashed__order_products.product_extras') . ' like ?', [$like]));
                    })
                    ->sortable(),
                TextColumn::make('sku')
                    ->label(__('SKU'))
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label(__('Aantal'))
                    ->sortable(),
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
                        ->select('dashed__order_products.*')),
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
                    ->visible(fn ($livewire): bool => ! in_array($livewire->activeTab ?? null, ['grouped', 'grouped_product_group'], true)),
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
                        ->select('dashed__order_products.*')),
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
                    }),
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
                    ->visible(fn ($livewire): bool => ! in_array($livewire->activeTab ?? null, ['grouped', 'grouped_product_group'], true)),
                TextColumn::make('order.name')
                    ->label(__('Klant'))
                    ->toggleable()
                    // Bij de gegroepeerde weergaven hoort geen klantnaam (een
                    // regel bundelt meerdere orders/klanten).
                    ->visible(fn ($livewire): bool => ! in_array($livewire->activeTab ?? null, ['grouped', 'grouped_product_group'], true)),
                TextColumn::make('order.created_at')
                    ->label(__('Besteld op'))
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('order_id', 'desc')
            ->filters([
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
                    ->options(Orders::getFulfillmentStatusses())
                    ->multiple()
                    // Standaard "openstaand": nog niet afgehandeld én in behandeling.
                    ->default(['unhandled', 'in_treatment'])
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
                TernaryFilter::make('has_product_extras')
                    ->label(__('Product opties'))
                    ->placeholder(__('Alles'))
                    ->trueLabel('Met opties')
                    ->falseLabel('Zonder opties')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('product_extras')
                            ->whereRaw("JSON_VALID(product_extras) = 1")
                            ->whereRaw("JSON_LENGTH(product_extras) > 0"),
                        false: fn (Builder $query) => $query
                            ->where(fn (Builder $q) => $q
                                ->whereNull('product_extras')
                                ->orWhereRaw("JSON_VALID(product_extras) = 1 AND JSON_LENGTH(product_extras) = 0")),
                        blank: fn (Builder $query) => $query,
                    ),

                TernaryFilter::make('has_product_id')
                    ->label(__('Product ID'))
                    ->placeholder(__('Alles'))
                    ->trueLabel('Met product ID')
                    ->falseLabel('Zonder product ID')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereNotNull('product_id')
                            ->where('product_id', '!=', 0),
                        false: fn (Builder $query) => $query
                            ->where(fn (Builder $q) => $q
                                ->whereNull('product_id')
                                ->orWhere('product_id', 0)),
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
            ->recordUrl(function ($record, $livewire) {
                // In de grouped tabs is order_id een MIN() over meerdere
                // orders, dus dat zou misleidend zijn — alleen linken op de
                // per-orderregel-weergave.
                if (in_array($livewire->activeTab ?? null, ['grouped', 'grouped_product_group'], true)) {
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
