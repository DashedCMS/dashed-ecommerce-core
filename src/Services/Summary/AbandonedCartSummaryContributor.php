<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Summary;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;

/**
 * Samenvatting-bijdrage voor verlaten-winkelwagen-flows. Toont
 * inschrijvingen, verzonden mails, klikken, gerecoverde orders en
 * de gerecoverde omzet in de gekozen periode.
 */
class AbandonedCartSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'verlaten_winkelwagens';
    }

    public static function label(): string
    {
        return 'Verlaten winkelwagens';
    }

    public static function description(): string
    {
        return 'Inschrijvingen, verzonden mails, klikken en gerecoverde orders uit verlaten-winkelwagen-flows in de periode.';
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
        // Inschrijvingen: nieuwe rijen aangemaakt in deze periode.
        $newEnrollments = AbandonedCartEmail::query()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->count();

        // Verzonden mails: rijen die in deze periode daadwerkelijk
        // verstuurd zijn (sent_at gevuld in periode).
        $sent = AbandonedCartEmail::query()
            ->whereBetween('sent_at', [$period->start, $period->end])
            ->count();

        // Klikken: rijen waarvan de ontvanger in deze periode op de
        // mail-link klikte.
        $clicked = AbandonedCartEmail::query()
            ->whereBetween('clicked_at', [$period->start, $period->end])
            ->count();

        // Gerecoverde rijen: e-mails waarvan de bijbehorende klant
        // alsnog een bestelling afrondde, gekoppeld via order_id en
        // converted_at in deze periode.
        $recoveredRows = AbandonedCartEmail::query()
            ->whereBetween('converted_at', [$period->start, $period->end])
            ->whereNotNull('order_id')
            ->get(['order_id']);

        $recoveredCount = $recoveredRows->pluck('order_id')->unique()->count();

        $recoveredRevenue = 0.0;
        if ($recoveredCount > 0) {
            $recoveredRevenue = (float) Order::query()
                ->isPaid()
                ->whereIn('id', $recoveredRows->pluck('order_id')->unique()->all())
                ->sum('total');
        }

        // Niets gebeurd in deze periode, sla de sectie over.
        if ($newEnrollments === 0 && $sent === 0 && $clicked === 0 && $recoveredCount === 0) {
            return null;
        }

        $rows = [
            ['label' => 'Nieuwe inschrijvingen', 'value' => (string) $newEnrollments],
            ['label' => 'Mails verzonden', 'value' => (string) $sent],
            ['label' => 'Klikken op mail-link', 'value' => (string) $clicked],
            ['label' => 'Gerecoverde bestellingen', 'value' => (string) $recoveredCount],
            ['label' => 'Gerecoverde omzet', 'value' => CurrencyHelper::formatPrice($recoveredRevenue)],
        ];

        return new SummarySection(
            title: 'Verlaten winkelwagens',
            blocks: [
                ['type' => 'stats', 'data' => ['rows' => $rows]],
            ],
        );
    }
}
