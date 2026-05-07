<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Summary;

use Throwable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\CustomerMatchEndpoint;
use Dashed\DashedEcommerceCore\Models\CustomerMatchAccessLog;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;

/**
 * Samenvatting-bijdrage voor de Google Ads Customer Match feature.
 * Toont hoeveel betaalde orders met e-mail of telefoon in de
 * periode bijkwamen (en dus in de gehashte export terechtkomen) en
 * hoe vaak Google Ads de feed in de periode opgehaald heeft.
 *
 * GDPR: deze contributor laat alleen aantallen zien, nooit ruwe
 * e-mailadressen of telefoonnummers.
 */
class CustomerMatchSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'customer_match';
    }

    public static function label(): string
    {
        return 'Customer Match (Google Ads)';
    }

    public static function description(): string
    {
        return 'Aantal e-mailadressen + telefoonnummers die in de periode aan Customer Match exports zijn toegevoegd.';
    }

    public static function defaultFrequency(): string
    {
        return 'monthly';
    }

    public static function availableFrequencies(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public static function contribute(SummaryPeriod $period): ?SummarySection
    {
        // Endpoint moet bestaan en actief zijn, anders heeft een
        // samenvatting hierover geen waarde voor de admin.
        try {
            $endpoint = CustomerMatchEndpoint::query()->where('id', 1)->first();
        } catch (Throwable) {
            $endpoint = null;
        }

        if (! $endpoint || ! $endpoint->is_active) {
            return null;
        }

        // Tel betaalde orders in de periode met e-mail of telefoon.
        // Dit benadert "nieuwe gehashte matches": de exporter dedupt
        // later op e-mail, dus dit is een bovengrens, niet exact.
        $newOrdersWithEmail = Order::query()
            ->isPaid()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        $newOrdersWithPhone = Order::query()
            ->isPaid()
            ->whereBetween('created_at', [$period->start, $period->end])
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->count();

        // Aantal keer dat Google Ads de feed in de periode ophaalde.
        $accessCount = 0;
        try {
            $accessCount = CustomerMatchAccessLog::query()
                ->where('customer_match_endpoint_id', $endpoint->id)
                ->whereBetween('created_at', [$period->start, $period->end])
                ->count();
        } catch (Throwable) {
            $accessCount = 0;
        }

        if ($newOrdersWithEmail === 0 && $newOrdersWithPhone === 0 && $accessCount === 0) {
            return null;
        }

        $rows = [
            ['label' => 'Nieuwe orders met e-mail', 'value' => (string) $newOrdersWithEmail],
            ['label' => 'Nieuwe orders met telefoon', 'value' => (string) $newOrdersWithPhone],
            ['label' => 'Feed-ophalingen door Google Ads', 'value' => (string) $accessCount],
        ];

        return new SummarySection(
            title: 'Customer Match (Google Ads)',
            blocks: [
                ['type' => 'paragraph', 'data' => ['content' => '<p>Customer Match feed is actief. Onderstaande aantallen zijn bovengrenzen, de exporter dedupliceert later op e-mailadres.</p>']],
                ['type' => 'stats', 'data' => ['rows' => $rows]],
            ],
        );
    }
}
