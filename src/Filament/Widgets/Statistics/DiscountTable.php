<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Dashed\DashedCore\Classes\Sites;
use Filament\Tables\Columns\TextColumn;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

class DiscountTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';
    protected $listeners = [
        'updateGraphData' => 'updateGraphData',
    ];

    public $graphData;
    public $orderProducts;

    public function updateGraphData($data): void
    {
        $this->graphData = $data;
    }

    protected static ?string $heading = 'Verkochte producten';

    public function table(Table $table): Table
    {
        return $table
            ->poll('1s')
            ->query(function () {
                if ($this->graphData['filters']['discountCode'] && $this->graphData['filters']['discountCode'] != 'all') {
                    $discountCode = DiscountCode::where('code', $this->graphData['filters']['discountCode'])->first();
                    if (! $discountCode) {
                        return Order::where('id', 0);
                    }
                }

                $orders = Order::query()
                    ->where('created_at', '>=', $this->graphData['filters']['beginDate'])
                    ->where('created_at', '<=', $this->graphData['filters']['endDate']);

                if (isset($discountCode) && $discountCode) {
                    $orders->where('discount_code_id', $discountCode->id);
                }

                if ($this->graphData['filters']['status'] == null || $this->graphData['filters']['status'] == 'payment_obligation') {
                    $orders->isPaid();
                } elseif ($this->graphData['filters']['status'] != 'all') {
                    $orders->where('status', $this->graphData['filters']['status']);
                }

                return $orders;
            })
            ->columns([
                TextColumn::make('invoice_id')
                    ->searchable()
                    ->sortable()
                    ->label(__('Bestelling ID')),
                TextColumn::make('site_id')
                    ->searchable()
                    ->sortable()
                    ->label(__('Site'))
                    ->visible(count(Sites::getSites()) > 1),
                TextColumn::make('paymentMethod')
                    ->searchable()
                    ->sortable()
                    ->label(__('Betaalmethode')),
                TextColumn::make('status')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->label(__('Status'))
                    ->colors([
                        'primary' => fn ($state): bool => $state === 'Lopende aankoop',
                        'danger' => fn ($state): bool => $state === 'Geannuleerd',
                        'warning' => fn ($state): bool => in_array($state, ['Gedeeltelijk betaald', 'Retour']),
                        'success' => fn ($state): bool => in_array($state, ['Betaald', 'Wachten op betaling']),
                    ]),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('Klant')),
                TextColumn::make('discount')
                    ->searchable()
                    ->sortable()
                    ->money('EUR')
                    ->label(__('Korting')),
                TextColumn::make('total')
                    ->searchable()
                    ->sortable()
                    ->money('EUR')
                    ->label(__('Totaal')),
                TextColumn::make('created_at')
                    ->searchable()
                    ->sortable()
                    ->dateTime()
                    ->label(__('Aangemaakt op')),
            ]);
    }
}
