<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Orders;

use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;

class OrderOutstandingStatsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Openstaand';

    protected function getStats(): array
    {
        $pending = Order::query()->where('status', 'pending');
        $pendingCount = (clone $pending)->count();
        $pendingTotal = (clone $pending)->sum('total');

        $waitingForConfirmation = Order::query()->where('status', 'waiting_for_confirmation');
        $wfcCount = (clone $waitingForConfirmation)->count();
        $wfcTotal = (clone $waitingForConfirmation)->sum('total');

        $partiallyPaidIds = Order::query()
            ->where('status', 'partially_paid')
            ->pluck('id');
        $partiallyPaidCount = $partiallyPaidIds->count();
        $partiallyPaidTotal = Order::query()
            ->whereIn('id', $partiallyPaidIds)
            ->sum('total');
        $partiallyPaidPaid = OrderPayment::query()
            ->whereIn('order_id', $partiallyPaidIds)
            ->where('status', 'paid')
            ->sum('amount');
        $partiallyPaidOutstanding = max(0, $partiallyPaidTotal - $partiallyPaidPaid);

        $totalOutstanding = $pendingTotal + $wfcTotal + $partiallyPaidOutstanding;

        return [
            Stat::make('Wachten op betaling', $pendingCount)
                ->description(CurrencyHelper::formatPrice($pendingTotal) . ' totaal')
                ->url($this->filterUrl(['pending']))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Te bevestigen', $wfcCount)
                ->description(CurrencyHelper::formatPrice($wfcTotal) . ' totaal')
                ->url($this->filterUrl(['waiting_for_confirmation']))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Deels betaald', $partiallyPaidCount)
                ->description(CurrencyHelper::formatPrice($partiallyPaidOutstanding) . ' openstaand')
                ->url($this->filterUrl(['partially_paid']))
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Totaal openstaand', CurrencyHelper::formatPrice($totalOutstanding))
                ->description(($pendingCount + $wfcCount + $partiallyPaidCount) . ' facturen')
                ->url($this->filterUrl(['pending', 'waiting_for_confirmation', 'partially_paid']))
                ->extraAttributes(['class' => 'cursor-pointer']),
        ];
    }

    protected function filterUrl(array $statuses): string
    {
        return OrderResource::getUrl('index', [
            'tableFilters' => [
                'status' => [
                    'values' => $statuses,
                ],
            ],
        ]);
    }
}
