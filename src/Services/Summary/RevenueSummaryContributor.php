<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Summary;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedCore\Services\Summary\DTOs\SummaryPeriod;
use Dashed\DashedCore\Services\Summary\DTOs\SummarySection;
use Dashed\DashedCore\Services\Summary\Contracts\SummaryContributorInterface;

/**
 * Samenvatting-bijdrage voor omzet en bestellingen. Toont in de mail
 * het aantal betaalde bestellingen, totale omzet, gemiddelde
 * orderwaarde (AOV) en de top 5 best verkochte producten in de
 * gekozen periode.
 */
class RevenueSummaryContributor implements SummaryContributorInterface
{
    public static function key(): string
    {
        return 'omzet';
    }

    public static function label(): string
    {
        return 'Omzet';
    }

    public static function description(): string
    {
        return 'Aantal betaalde bestellingen, totale omzet, gemiddelde orderwaarde en de top 5 best verkochte producten in de periode.';
    }

    public static function defaultFrequency(): string
    {
        return 'daily';
    }

    public static function availableFrequencies(): array
    {
        return ['daily', 'weekly', 'monthly'];
    }

    public static function contribute(SummaryPeriod $period): ?SummarySection
    {
        $ordersQuery = Order::query()
            ->isPaid()
            ->whereBetween('created_at', [$period->start, $period->end]);

        $orderCount = (clone $ordersQuery)->count();

        // Geen bestellingen, geen sectie. Lege secties worden in de
        // mail overgeslagen zodat we geen muur van nullen tonen.
        if ($orderCount === 0) {
            return null;
        }

        $totalRevenue = (float) (clone $ordersQuery)->sum('total');
        $averageOrderValue = $orderCount > 0 ? $totalRevenue / $orderCount : 0.0;

        $stats = [
            ['label' => 'Betaalde bestellingen', 'value' => (string) $orderCount],
            ['label' => 'Totale omzet', 'value' => CurrencyHelper::formatPrice($totalRevenue)],
            ['label' => 'Gemiddelde orderwaarde', 'value' => CurrencyHelper::formatPrice($averageOrderValue)],
        ];

        $blocks = [
            ['type' => 'stats', 'data' => ['rows' => $stats]],
        ];

        // Top 5 producten: join op order_products, filter op
        // dezelfde periode (zodat we niet uit oude orders gaan
        // tellen) en groepeer op product. Gebruikt de FK
        // dashed__order_products.order_id om naar dashed__orders te
        // joinen, en filtert op order-status en created_at.
        $topProducts = OrderProduct::query()
            ->join('dashed__orders', 'dashed__orders.id', '=', 'dashed__order_products.order_id')
            ->whereIn('dashed__orders.status', ['paid', 'waiting_for_confirmation', 'partially_paid'])
            ->whereBetween('dashed__orders.created_at', [$period->start, $period->end])
            ->whereNull('dashed__order_products.deleted_at')
            ->groupBy('dashed__order_products.product_id', 'dashed__order_products.name')
            ->orderByDesc(DB::raw('SUM(dashed__order_products.quantity)'))
            ->limit(5)
            ->get([
                'dashed__order_products.product_id',
                'dashed__order_products.name',
                DB::raw('SUM(dashed__order_products.quantity) as total_quantity'),
                DB::raw('SUM(dashed__order_products.price) as total_revenue'),
            ]);

        if ($topProducts->isNotEmpty()) {
            $rows = [];
            foreach ($topProducts as $row) {
                $rows[] = [
                    (string) ($row->name ?: 'Onbekend product'),
                    (string) (int) $row->total_quantity,
                    CurrencyHelper::formatPrice((float) $row->total_revenue),
                ];
            }

            $blocks[] = ['type' => 'heading', 'data' => ['content' => 'Top 5 producten']];
            $blocks[] = [
                'type' => 'table',
                'data' => [
                    'headers' => ['Product', 'Aantal', 'Omzet'],
                    'rows' => $rows,
                ],
            ];
        }

        return new SummarySection(
            title: 'Omzet',
            blocks: $blocks,
        );
    }
}
