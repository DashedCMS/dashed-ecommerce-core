<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Widgets;

use Illuminate\Database\Eloquent\Model;
use Filament\Widgets\StatsOverviewWidget;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Dashed\DashedEcommerceCore\Models\OrderHandledClick;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

/**
 * Stats-cards onderaan de flow edit-pagina. Telt inschrijvingen, klikken,
 * cancellations (per reden) en geconverteerde klanten (klanten die na
 * inschrijving alsnog opnieuw een betaalde bestelling deden).
 */
class OrderHandledFlowStats extends StatsOverviewWidget
{
    public ?Model $record = null;

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

        $stats = [
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
            (function () use ($enrollmentsBase, $total): Stat {
                // Tel het totaal aantal verzonden mails over alle inschrijvingen.
                // sent_steps is een JSON-array met { step_id => iso-timestamp }, dus
                // we gebruiken JSON_LENGTH op DB-niveau om N+1 te voorkomen.
                $sentMails = (int) (clone $enrollmentsBase)
                    ->whereNotNull('sent_steps')
                    ->selectRaw('COALESCE(SUM(JSON_LENGTH(sent_steps)), 0) as total')
                    ->value('total');

                $avg = $total > 0 ? round($sentMails / $total, 2) : 0;

                return Stat::make('Mails verzonden', $sentMails)
                    ->description($avg.' gemiddeld per inschrijving')
                    ->icon('heroicon-o-paper-airplane')
                    ->color($sentMails > 0 ? 'success' : 'gray');
            })(),
            Stat::make('Geconverteerd', $convertedCount)
                ->description($conversionRate . '% conversieratio')
                ->icon('heroicon-o-shopping-cart')
                ->color('success'),
            Stat::make('Vervolg-omzet', '€ ' . number_format($convertedRevenue, 2, ',', '.'))
                ->description('Som van orders na inschrijving (zelfde e-mail)')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
        ];

        // Per-platform-stats: alleen tonen wanneer er review-URLs ingesteld zijn
        // op de flow. We groeperen op chosen_review_url_label en berekenen per
        // platform de conversie (klanten die opnieuw besteld hebben + omzet).
        $reviewUrls = $this->record->review_urls ?? [];
        if (is_array($reviewUrls) && count($reviewUrls) > 0) {
            $byLabel = (clone $enrollmentsBase)
                ->selectRaw('COALESCE(chosen_review_url_label, "Onbekend") as label, COUNT(*) as count')
                ->groupBy('label')
                ->orderByDesc('count')
                ->limit(5)
                ->get();

            $platformConversions = [];
            foreach ($byLabel as $row) {
                $labelKey = $row->label;
                $platformEnrollments = (clone $enrollmentsBase)
                    ->when(
                        $labelKey === 'Onbekend',
                        fn ($q) => $q->whereNull('chosen_review_url_label'),
                        fn ($q) => $q->where('chosen_review_url_label', $labelKey),
                    )
                    ->with('order:id,email')
                    ->get(['id', 'order_id', 'started_at']);

                $pCount = 0;
                $pRevenue = 0.0;
                foreach ($platformEnrollments as $enrollment) {
                    if (! $enrollment->order || blank($enrollment->order->email)) {
                        continue;
                    }
                    $followUp = Order::query()
                        ->where('email', $enrollment->order->email)
                        ->where('id', '!=', $enrollment->order_id)
                        ->isPaid()
                        ->where('created_at', '>=', $enrollment->started_at)
                        ->first(['id', 'total']);

                    if ($followUp) {
                        $pCount++;
                        $pRevenue += (float) $followUp->total;
                    }
                }

                $platformConversions[$labelKey] = [
                    'enrollments' => (int) $row->count,
                    'converted' => $pCount,
                    'revenue' => $pRevenue,
                    'rate' => $row->count > 0 ? ($pCount / $row->count) : 0.0,
                ];
            }

            // Bepaal het platform met de hoogste conversieratio voor highlight.
            $bestLabel = null;
            if (count($platformConversions) > 1) {
                $bestRate = -1.0;
                foreach ($platformConversions as $label => $info) {
                    if ($info['rate'] > $bestRate) {
                        $bestRate = $info['rate'];
                        $bestLabel = $label;
                    }
                }
            }

            foreach ($platformConversions as $label => $info) {
                $description = $info['converted'] . ' klanten hebben opnieuw besteld - € '
                    . number_format($info['revenue'], 2, ',', '.');

                $stat = Stat::make($label, $info['enrollments'])
                    ->description($description)
                    ->icon('heroicon-o-star');

                if (count($platformConversions) > 1) {
                    $stat = $stat->color($label === $bestLabel ? 'success' : 'gray');
                }

                $stats[] = $stat;
            }
        }

        return $stats;
    }
}
