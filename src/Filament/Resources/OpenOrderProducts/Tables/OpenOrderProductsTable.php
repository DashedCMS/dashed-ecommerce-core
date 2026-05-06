<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OpenOrderProducts\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;

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
                        'in_treatment', 'packed', 'ready_for_pickup' => 'info',
                        'shipped' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('order.name')
                    ->label('Klant')
                    ->toggleable(),
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
            ])
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession();
    }
}
