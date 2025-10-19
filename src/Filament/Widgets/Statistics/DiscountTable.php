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
    protected ?string $pollingInterval = '1s';

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
                    ->label('Bestelling ID'),
                TextColumn::make('site_id')
                    ->searchable()
                    ->sortable()
                    ->label('Site')
                    ->visible(count(Sites::getSites()) > 1),
                TextColumn::make('paymentMethod')
                    ->searchable()
                    ->sortable()
                    ->label('Betaalmethode'),
                TextColumn::make('status')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->label('Status')
                    ->colors([
                        'primary' => fn ($state): bool => $state === 'Lopende aankoop',
                        'danger' => fn ($state): bool => $state === 'Geannuleerd',
                        'warning' => fn ($state): bool => in_array($state, ['Gedeeltelijk betaald', 'Retour']),
                        'success' => fn ($state): bool => in_array($state, ['Betaald', 'Wachten op bevestiging betaling']),
                    ]),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Klant'),
                TextColumn::make('discount')
                    ->searchable()
                    ->sortable()
                    ->money('EUR')
                    ->label('Korting'),
                TextColumn::make('total')
                    ->searchable()
                    ->sortable()
                    ->money('EUR')
                    ->label('Totaal'),
                TextColumn::make('created_at')
                    ->searchable()
                    ->sortable()
                    ->dateTime()
                    ->label('Aangemaakt op'),
            ]);
    }
}
