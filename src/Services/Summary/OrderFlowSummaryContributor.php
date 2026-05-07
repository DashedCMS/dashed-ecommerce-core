<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Summary;

use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Models\OrderHandledClick;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;

/**
 * Samenvatting-bijdrage voor de order opvolg flows (review-mails na
 * fulfillment). Toont nieuwe inschrijvingen, geannuleerde
 * inschrijvingen en klikken in de periode plus een tabel per
 * actieve flow met aantallen.
 */
class OrderFlowSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'order_opvolg_flows';
    }

    public static function label(): string
    {
        return 'Order opvolg flows';
    }

    public static function description(): string
    {
        return 'Inschrijvingen per actieve flow, mails verzonden, klikken en conversies in de periode.';
    }

    public static function defaultFrequency(): string
    {
        return 'weekly';
    }

    public static function availableFrequencies(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public static function contribute(SummaryPeriod $period): ?SummarySection
    {
        $totalEnrollments = OrderFlowEnrollment::query()
            ->whereBetween('started_at', [$period->start, $period->end])
            ->count();

        $totalCancelled = OrderFlowEnrollment::query()
            ->whereBetween('cancelled_at', [$period->start, $period->end])
            ->count();

        $totalClicks = OrderHandledClick::query()
            ->whereBetween('clicked_at', [$period->start, $period->end])
            ->count();

        // Geen activiteit, sla de sectie over.
        if ($totalEnrollments === 0 && $totalCancelled === 0 && $totalClicks === 0) {
            return null;
        }

        $stats = [
            ['label' => 'Nieuwe inschrijvingen', 'value' => (string) $totalEnrollments],
            ['label' => 'Geannuleerd', 'value' => (string) $totalCancelled],
            ['label' => 'Klikken op review-link', 'value' => (string) $totalClicks],
        ];

        $blocks = [
            ['type' => 'stats', 'data' => ['rows' => $stats]],
        ];

        // Per actieve flow een rij tonen, ook als die flow zelf
        // geen activiteit had in de periode (zodat duidelijk is dat
        // hij actief stond maar nul verkeer kreeg). Inactieve flows
        // sluiten we uit, die zijn niet relevant voor de admin.
        $activeFlows = OrderHandledFlow::query()
            ->where('is_active', true)
            ->orderBy('trigger_status')
            ->orderBy('name')
            ->get();

        if ($activeFlows->isNotEmpty()) {
            $rows = [];
            foreach ($activeFlows as $flow) {
                $flowEnrollments = OrderFlowEnrollment::query()
                    ->where('flow_id', $flow->id)
                    ->whereBetween('started_at', [$period->start, $period->end])
                    ->count();

                $flowCancelled = OrderFlowEnrollment::query()
                    ->where('flow_id', $flow->id)
                    ->whereBetween('cancelled_at', [$period->start, $period->end])
                    ->count();

                $rows[] = [
                    (string) $flow->name,
                    (string) $flow->trigger_status,
                    (string) $flowEnrollments,
                    (string) $flowCancelled,
                ];
            }

            $blocks[] = ['type' => 'heading', 'data' => ['content' => 'Per actieve flow']];
            $blocks[] = [
                'type' => 'table',
                'data' => [
                    'headers' => ['Flow', 'Trigger status', 'Inschrijvingen', 'Geannuleerd'],
                    'rows' => $rows,
                ],
            ];
        }

        return new SummarySection(
            title: 'Order opvolg flows',
            blocks: $blocks,
        );
    }
}
