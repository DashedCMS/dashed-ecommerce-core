<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class OpenOrderProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.invoice_id')
                    ->label('Bestelling')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product_id')
                    ->label('Product ID')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Productnaam')
                    ->wrap()
                    ->description(function ($record) {
                        if (! is_array($record->product_extras) || ! $record->product_extras) {
                            return null;
                        }

                        return collect($record->product_extras)
                            ->map(fn ($option) => ($option['name'] ?? '') . ': ' . ($option['value'] ?? ''))
                            ->implode(' | ');
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Aantal')
                    ->sortable(),
                TextColumn::make('product.stock')
                    ->label('Voorraad')
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
                    ->sortable(query: fn (Builder $q, string $dir) => $q
                        ->leftJoin(
                            'dashed__products as voorraad_join',
                            'voorraad_join.id',
                            '=',
                            'dashed__order_products.product_id'
                        )
                        ->orderBy('voorraad_join.stock', $dir)
                        ->select('dashed__order_products.*')),
                TextColumn::make('order.order_origin')
                    ->label('Order origin')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                    ->sortable(query: fn (Builder $q, string $dir) => $q
                        ->leftJoin(
                            'dashed__orders as order_origin_join',
                            'order_origin_join.id',
                            '=',
                            'dashed__order_products.order_id'
                        )
                        ->orderBy('order_origin_join.order_origin', $dir)
                        ->select('dashed__order_products.*')),
                TextColumn::make('order.fulfillment_status')
                    ->label('Fulfillment status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'handled' => 'success',
                        'unhandled' => 'warning',
                        'packed' => 'warning',
                        'in_treatment', 'ready_for_pickup' => 'info',
                        'shipped' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('order.name')
                    ->label('Klant')
                    ->toggleable()
                    // Bij de gegroepeerde weergaven hoort geen klantnaam (een
                    // regel bundelt meerdere orders/klanten).
                    ->visible(fn ($livewire): bool => ! in_array($livewire->activeTab ?? null, ['grouped', 'grouped_product_group'], true)),
                TextColumn::make('order.created_at')
                    ->label('Besteld op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('order_id', 'desc')
            ->filters([
                SelectFilter::make('fulfillment_status')
                    ->label('Fulfillment status')
                    ->options(Orders::getFulfillmentStatusses())
                    ->default('unhandled')
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('order', fn ($s) => $s->where('fulfillment_status', $data['value']));
                    }),

                SelectFilter::make('order_origin')
                    ->label('Order origin')
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

                TernaryFilter::make('has_product_extras')
                    ->label('Product opties')
                    ->placeholder('Alles')
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
                    ->label('Product ID')
                    ->placeholder('Alles')
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
                    ->label('Product')
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
                    ->label('Productgroep')
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
                    ->label('Productcategorie')
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
}
