<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderHandledClick;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;

/**
 * Stats-cards onderaan de flow edit-pagina. Telt inschrijvingen, klikken,
 * cancellations (per reden) en geconverteerde klanten (klanten die na
 * inschrijving alsnog opnieuw een betaalde bestelling deden).
 */
class OrderHandledFlowStats extends StatsOverviewWidget
{
    public ?OrderHandledFlow $record = null;

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $enrollmentsBase = OrderFlowEnrollment::query()->where('flow_id', $this->record->id);

        $total = (clone $enrollmentsBase)->count();
        $active = (clone $enrollmentsBase)->whereNull('cancelled_at')->count();
        $cancelled = (clone $enrollmentsBase)->whereNotNull('cancelled_at')->count();

        $reasonBreakdown = (clone $enrollmentsBase)
            ->whereNotNull('cancelled_at')
            ->selectRaw('COALESCE(cancelled_reason, "onbekend") as reason, COUNT(*) as count')
            ->groupBy('reason')
            ->pluck('count', 'reason')
            ->toArray();

        $stepIds = $this->record->steps()->pluck('id');
        $clicks = OrderHandledClick::query()->whereIn('flow_step_id', $stepIds)->count();
        $uniqueClickers = OrderHandledClick::query()
            ->whereIn('flow_step_id', $stepIds)
            ->distinct('order_id')
            ->count('order_id');

        $clickRate = $total > 0 ? round(($uniqueClickers / $total) * 100, 1) : 0;

        // Geconverteerde klanten: enrollments waarvan de e-mail later opnieuw
        // een betaalde bestelling plaatste na de start-datum van de inschrijving.
        $enrolledOrders = (clone $enrollmentsBase)
            ->with('order:id,email')
            ->get(['id', 'order_id', 'started_at'])
            ->filter(fn ($e) => $e->order && ! blank($e->order->email));

        $convertedCount = 0;
        $convertedRevenue = 0.0;
        foreach ($enrolledOrders as $enrollment) {
            $followUp = Order::query()
                ->where('email', $enrollment->order->email)
                ->where('id', '!=', $enrollment->order_id)
                ->isPaid()
                ->where('created_at', '>=', $enrollment->started_at)
                ->first(['id', 'total']);

            if ($followUp) {
                $convertedCount++;
                $convertedRevenue += (float) $followUp->total;
            }
        }
        $conversionRate = $total > 0 ? round(($convertedCount / $total) * 100, 1) : 0;

        $cancelledDescription = $cancelled > 0 && ! empty($reasonBreakdown)
            ? collect($reasonBreakdown)->map(fn ($n, $r) => "{$n}× {$r}")->implode(', ')
            : 'Niemand geannuleerd';

        return [
            Stat::make('Inschrijvingen', $total)
                ->description('Totaal aantal orders dat ooit in deze flow zat')
                ->icon('heroicon-o-user-group'),
            Stat::make('Actief in flow', $active)
                ->description('Lopende inschrijvingen zonder annulering')
                ->icon('heroicon-o-play-circle')
                ->color('success'),
            Stat::make('Geannuleerd', $cancelled)
                ->description($cancelledDescription)
                ->icon('heroicon-o-x-circle')
                ->color($cancelled > 0 ? 'warning' : 'gray'),
            Stat::make('Klikken', $clicks)
                ->description($uniqueClickers . ' unieke klikkers - ' . $clickRate . '% van inschrijvingen')
                ->icon('heroicon-o-cursor-arrow-rays')
                ->color('info'),
            Stat::make('Geconverteerd', $convertedCount)
                ->description($conversionRate . '% conversieratio')
                ->icon('heroicon-o-shopping-cart')
                ->color('success'),
            Stat::make('Vervolg-omzet', '€ ' . number_format($convertedRevenue, 2, ',', '.'))
                ->description('Som van orders na inschrijving (zelfde e-mail)')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}
