<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

class ProductTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';
    protected static ?string $pollingInterval = '1s';

    protected $listeners = [
        'updateGraphData' => 'updateGraphData',
    ];

    public $graphData;
    public $orderProducts;

    public function updateGraphData($data): void
    {
        $this->graphData = $data;
    }

    protected static ?string $heading = 'Verkochten producten';

    public function table(Table $table): Table
    {
        return $table
            ->poll('1s')
            ->query(function () {
                $orderIds = Order::isPaid()
                    ->where('created_at', '>=', $this->graphData['filters']['beginDate'])
                    ->where('created_at', '<=', $this->graphData['filters']['endDate'])
                    ->pluck('id');

                $orderProducts = OrderProduct::whereIn('order_id', $orderIds)->get();
                $this->orderProducts = $orderProducts;

                return Product::notParentProduct()
                    ->whereRaw('LOWER(name) like ?', '%' . strtolower($this->graphData['filters']['search']) . '%');
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Product'),
                TextColumn::make('quantitySold')
                    ->label('Aantal verkocht')
                    ->getStateUsing(fn ($record) => $this->orderProducts->where('product_id', $record->id)->sum('quantity')),
                TextColumn::make('currentStock')
                    ->label('Voorraad')
                    ->getStateUsing(fn ($record) => $record->use_stock ? $record->stock : ($record->stock_status == 'in_stock' ? 100000 : 0)),
                TextColumn::make('amountSold')
                    ->money('EUR')
                    ->label('Totaal opgeleverd')
                    ->getStateUsing(fn ($record) => $this->orderProducts->where('product_id', $record->id)->sum('price')),
            ]);
    }
}
