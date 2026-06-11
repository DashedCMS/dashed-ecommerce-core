<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Inzichten voor app + CMS: een cashflow-puls (omzet/btw/openstaand) en een
 * voorspellend inkoopadvies (welke producten raken op basis van verkoopsnelheid
 * binnenkort op). Gedeelde logica zodat de mobiele API en de Filament-pagina
 * exact dezelfde cijfers tonen.
 */
class InsightsService
{
    /** Aantal dagen waarover de verkoopsnelheid wordt gemeten. */
    public int $velocityDays = 30;

    /** Toon producten die binnen zoveel dagen opraken. */
    public int $horizonDays = 21;

    /** Adviseer voorraad voor zoveel dagen dekking. */
    public int $coverDays = 30;

    /** @return array<string, float|int> */
    public function cashflow(string $siteId): array
    {
        $monthStart = now()->startOfMonth();

        $paidThisMonth = Order::query()->where('site_id', $siteId)->isPaid()
            ->whereBetween('created_at', [$monthStart, now()])->get(['total']);
        $paidToday = Order::query()->where('site_id', $siteId)->isPaid()
            ->whereBetween('created_at', [now()->startOfDay(), now()])->get(['total']);

        $revenueMonth = round((float) $paidThisMonth->sum('total'), 2);
        $ordersMonth = $paidThisMonth->count();

        // Btw ontvangen deze maand (per orderregel opgeslagen).
        $vatMonth = round((float) OrderProduct::query()
            ->whereHas('order', function ($q) use ($siteId, $monthStart): void {
                $q->where('site_id', $siteId)->isPaid()->whereBetween('created_at', [$monthStart, now()]);
            })
            ->sum('btw'), 2);

        // Nog te ontvangen: geplaatste maar (nog) niet betaalde bestellingen.
        $outstanding = round((float) Order::query()->where('site_id', $siteId)
            ->where('status', 'pending')->sum('total'), 2);

        return [
            'revenue_today' => round((float) $paidToday->sum('total'), 2),
            'revenue_month' => $revenueMonth,
            'orders_month' => $ordersMonth,
            'average_order_value' => $ordersMonth > 0 ? round($revenueMonth / $ordersMonth, 2) : 0.0,
            'vat_month' => $vatMonth,
            'outstanding' => $outstanding,
        ];
    }

    /** @return array<int, array<string, mixed>> producten die opraken, urgentst eerst */
    public function reorderAdvice(string $siteId): array
    {
        $since = now()->subDays($this->velocityDays);

        // Verkochte stuks per product over de meetperiode (betaalde bestellingen).
        $sold = OrderProduct::query()
            ->whereNotNull('product_id')
            ->whereHas('order', function ($q) use ($siteId, $since): void {
                $q->where('site_id', $siteId)->isPaid()->where('created_at', '>=', $since);
            })
            ->selectRaw('product_id, SUM(quantity) as units')
            ->groupBy('product_id')
            ->pluck('units', 'product_id');

        if ($sold->isEmpty()) {
            return [];
        }

        $products = Product::thisSite($siteId)
            ->whereIn('id', $sold->keys()->all())
            ->where('use_stock', true)
            ->get(['id', 'name', 'sku', 'stock']);

        $advice = [];
        foreach ($products as $product) {
            $units = (int) ($sold[$product->id] ?? 0);
            if ($units <= 0) {
                continue;
            }

            $perDay = $units / $this->velocityDays;
            $stock = (int) $product->stock;
            $daysLeft = $perDay > 0 ? $stock / $perDay : null;

            // Alleen wat binnen de horizon opraakt (of al op/onder nul is).
            if ($daysLeft === null || $daysLeft > $this->horizonDays) {
                continue;
            }

            $suggested = (int) max(1, (int) ceil($perDay * $this->coverDays) - $stock);

            $advice[] = [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => $stock,
                'sold_period' => $units,
                'per_week' => round($perDay * 7, 1),
                'days_left' => (int) floor(max(0, $daysLeft)),
                'suggested_qty' => $suggested,
            ];
        }

        usort($advice, fn ($a, $b) => $a['days_left'] <=> $b['days_left']);

        return $advice;
    }

    /** @return array<string, mixed> */
    public function all(string $siteId): array
    {
        return [
            'cashflow' => $this->cashflow($siteId),
            'reorder' => $this->reorderAdvice($siteId),
            'meta' => [
                'velocity_days' => $this->velocityDays,
                'horizon_days' => $this->horizonDays,
                'cover_days' => $this->coverDays,
            ],
        ];
    }
}
