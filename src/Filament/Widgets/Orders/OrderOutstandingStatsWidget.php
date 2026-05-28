<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Orders;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

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
                ->description(CurrencyHelper::formatPrice($pendingTotal) . ' totaal'),

            Stat::make('Te bevestigen', $wfcCount)
                ->description(CurrencyHelper::formatPrice($wfcTotal) . ' totaal'),

            Stat::make('Deels betaald', $partiallyPaidCount)
                ->description(CurrencyHelper::formatPrice($partiallyPaidOutstanding) . ' openstaand'),

            Stat::make('Totaal openstaand', CurrencyHelper::formatPrice($totalOutstanding))
                ->description(($pendingCount + $wfcCount + $partiallyPaidCount) . ' facturen'),
        ];
    }
}
