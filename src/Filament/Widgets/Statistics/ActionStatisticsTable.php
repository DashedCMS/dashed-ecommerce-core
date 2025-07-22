<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;

class ActionStatisticsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '1s';

    protected $listeners = [
        'updateGraphData' => 'updateGraphData',
    ];

    public $graphData;
    public $addedToCartActions;
    public $removedFromCartActions;

    public function updateGraphData($data): void
    {
        $this->graphData = $data;
    }

    protected static ?string $heading = 'Actie statistieken';

    public function table(Table $table): Table
    {
        return $table
            ->poll('1s')
            ->query(function () {
                $beginDate = $this->graphData['filters']['beginDate'];
                $endDate = $this->graphData['filters']['endDate'];

                $this->addedToCartActions = EcommerceActionLog::where('action_type', 'add_to_cart')
                    ->where('created_at', '>=', $beginDate)
                    ->where('created_at', '<=', $endDate)
                    ->get();
                $this->removedFromCartActions = EcommerceActionLog::where('action_type', 'remove_from_cart')
                    ->where('created_at', '>=', $beginDate)
                    ->where('created_at', '<=', $endDate)
                    ->get();

                $products = Product::query()
                    ->whereIn('id', array_merge(
                        $this->addedToCartActions->pluck('product_id')->toArray(),
                        $this->removedFromCartActions->pluck('product_id')->toArray()
                    ))
                    ->where('created_at', '>=', $beginDate)
                    ->where('created_at', '<=', $endDate);

                return $products;
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Product'),
                TextColumn::make('add_to_cart_count')
                    ->getStateUsing(fn ($record) => $this->addedToCartActions->where('product_id', $record->id)->sum('quantity'))
                    ->searchable()
                    ->sortable()
                    ->label('Added to cart'),
                TextColumn::make('remove_from_cart_count')
                    ->getStateUsing(fn ($record) => $this->removedFromCartActions->where('product_id', $record->id)->sum('quantity'))
                    ->searchable()
                    ->sortable()
                    ->label('Remove from cart'),
            ]);
    }
}
