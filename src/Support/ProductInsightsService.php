<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Carbon\CarbonInterface;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Productinzichten voor de app: toppers, langzaamlopers, marge en dead stock.
 *
 * Bewust goedkoop gehouden — we hergebruiken bestaande productvelden:
 *  - top/slow leunen op `total_purchases` (lifetime, auto-onderhouden door de
 *    Product-model-hooks). Periode-scoped verkoopaantallen zouden per request
 *    een join order_products↔orders vereisen; voor "best/slowest sellers" is
 *    de lifetime-teller representatief én indexvriendelijk, dus die gebruiken
 *    we (en documenteren het in `sold`).
 *  - margin rekent puur op productvelden (`current_price - purchase_price`).
 *  - dead_stock is het enige metriek dat wél een periode-join doet: producten
 *    met voorraad die in de gekozen periode 0 stuks verkochten.
 *
 * "Huidige verkoopprijs" = `new_price` (afprijzing) wanneer gezet en > 0,
 * anders `price`. We lezen de rauwe kolommen zodat de ex/incl-BTW-accessor
 * (getCurrentPriceAttribute) de cijfers niet per ingelogde user verschuift.
 */
class ProductInsightsService
{
    public const METRICS = ['top', 'slow', 'margin', 'dead_stock'];

    public int $limit = 20;

    /** @return array<int, array<string, mixed>> */
    public function forMetric(string $metric, string $siteId, ?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        return match ($metric) {
            'slow' => $this->slow($siteId),
            'margin' => $this->margin($siteId),
            'dead_stock' => $this->deadStock($siteId, $start, $end),
            default => $this->top($siteId),
        };
    }

    /** Huidige verkoopprijs: afprijzing (new_price) indien gezet, anders price. */
    private function currentPrice(Product $product): ?float
    {
        $new = $product->getRawOriginal('new_price');
        if ($new !== null && (float) $new > 0) {
            return round((float) $new, 2);
        }

        $price = $product->getRawOriginal('price');

        return $price !== null ? round((float) $price, 2) : null;
    }

    /** Best verkochte producten op lifetime `total_purchases`, aflopend. */
    private function top(string $siteId): array
    {
        $products = Product::thisSite($siteId)
            ->orderByDesc('total_purchases')
            ->limit($this->limit)
            ->get(['id', 'name', 'price', 'new_price', 'stock', 'total_purchases']);

        return $products->map(fn (Product $product) => [
            'id' => (int) $product->id,
            'name' => $product->name,
            'sold' => (int) ($product->total_purchases ?? 0),
            'current_price' => $this->currentPrice($product),
            'stock' => $product->stock !== null ? (int) $product->stock : null,
        ])->all();
    }

    /** Langzaamlopers: laagste `total_purchases` onder producten met voorraad. */
    private function slow(string $siteId): array
    {
        $products = Product::thisSite($siteId)
            ->where('use_stock', true)
            ->where('stock', '>', 0)
            ->orderBy('total_purchases')
            ->orderByDesc('stock')
            ->limit($this->limit)
            ->get(['id', 'name', 'price', 'new_price', 'stock', 'total_purchases']);

        return $products->map(fn (Product $product) => [
            'id' => (int) $product->id,
            'name' => $product->name,
            'sold' => (int) ($product->total_purchases ?? 0),
            'current_price' => $this->currentPrice($product),
            'stock' => $product->stock !== null ? (int) $product->stock : null,
        ])->all();
    }

    /** Hoogste absolute marge (current_price - purchase_price); cost null/0 valt af. */
    private function margin(string $siteId): array
    {
        $products = Product::thisSite($siteId)
            ->whereNotNull('purchase_price')
            ->where('purchase_price', '>', 0)
            ->limit(200)
            ->get(['id', 'name', 'price', 'new_price', 'stock', 'purchase_price', 'total_purchases']);

        $items = [];
        foreach ($products as $product) {
            $current = $this->currentPrice($product);
            if ($current === null) {
                continue;
            }

            $cost = round((float) $product->getRawOriginal('purchase_price'), 2);
            $margin = round($current - $cost, 2);

            $items[] = [
                'id' => (int) $product->id,
                'name' => $product->name,
                'sold' => (int) ($product->total_purchases ?? 0),
                'current_price' => $current,
                'stock' => $product->stock !== null ? (int) $product->stock : null,
                'purchase_price' => $cost,
                'margin' => $margin,
                'margin_pct' => $current > 0 ? round($margin / $current * 100, 1) : null,
            ];
        }

        usort($items, fn ($a, $b) => $b['margin'] <=> $a['margin']);

        return array_slice($items, 0, $this->limit);
    }

    /**
     * Dead stock: producten met voorraad die in de periode 0 stuks verkochten.
     * We bepalen eerst welke product-id's wél verkochten (join order_products↔
     * orders, betaald, binnen de periode) en sluiten die uit.
     */
    private function deadStock(string $siteId, ?CarbonInterface $start, ?CarbonInterface $end): array
    {
        $soldIds = OrderProduct::query()
            ->whereNotNull('product_id')
            ->whereHas('order', function ($q) use ($siteId, $start, $end): void {
                $q->where('site_id', $siteId)->isPaid();
                if ($start && $end) {
                    $q->whereBetween('created_at', [$start, $end]);
                }
            })
            ->distinct()
            ->pluck('product_id')
            ->all();

        $products = Product::thisSite($siteId)
            ->where('stock', '>', 0)
            ->when(! empty($soldIds), fn ($q) => $q->whereNotIn('id', $soldIds))
            ->orderByDesc('stock')
            ->limit($this->limit)
            ->get(['id', 'name', 'price', 'new_price', 'stock']);

        return $products->map(fn (Product $product) => [
            'id' => (int) $product->id,
            'name' => $product->name,
            'stock' => (int) $product->stock,
            'current_price' => $this->currentPrice($product),
        ])->all();
    }
}
