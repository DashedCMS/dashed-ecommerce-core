<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

/**
 * De cijfers achter de dashboardwidgets, uitgerekend in de database.
 *
 * De widgets laadden eerst alle orders van een periode (of van altijd) als
 * Eloquent-modellen om ze daarna in PHP op te tellen, en de grafiek deed per
 * stap op de lijn twee queries: per uur over een jaar ruim zeventienduizend.
 * Hier komt alleen een telling en een som terug, en voor de grafiek één keer
 * de kale rijen die in PHP over de stappen verdeeld worden.
 */
class OrderStatistics
{
    /** Regels die geen product zijn en dus niet meetellen als verkocht product. */
    public const NON_PRODUCT_SKUS = ['product_costs', 'shipping_costs'];

    /**
     * Aantal orders, totaalbedrag en aantal verkochte producten van een orderquery.
     *
     * @return array{orders: int, amount: float, products: int}
     */
    public static function totals(Builder $orders): array
    {
        $row = (clone $orders)
            ->toBase()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as amount')
            ->first();

        $products = OrderProduct::query()
            ->whereIn('order_id', (clone $orders)->select('id'))
            ->whereNotIn('sku', self::NON_PRODUCT_SKUS)
            ->sum('quantity');

        return [
            'orders' => (int) ($row->orders ?? 0),
            'amount' => (float) ($row->amount ?? 0),
            'products' => (int) $products,
        ];
    }

    /**
     * Omzet van betaalde orders en van retouren per stap tussen twee datums.
     *
     * Elke stap loopt van het begin van zijn periode (uur, dag, week, ...) tot
     * het eind ervan; de reeks begint bij de stap waar de startdatum in valt
     * en gaat door zolang het begin van de stap voor de einddatum ligt.
     *
     * @return array{labels: array<int, string>, paid: array<int, float>, return: array<int, float>}
     */
    public static function perStep(Carbon $startDate, Carbon $endDate, string $steps): array
    {
        $formats = Dashboard::getFormatsByStep($steps);
        $startFormat = $formats['startFormat'];
        $endFormat = $formats['endFormat'];
        $addFormat = $formats['addFormat'];

        $starts = [];
        $cursor = $startDate->copy();
        while ($cursor < $endDate) {
            $starts[] = $cursor->copy()->$startFormat();
            $cursor->$addFormat();
        }

        $labels = array_map(
            fn (Carbon $start) => $steps === 'per_hour' ? $start->format('d-m-Y H:i') : $start->format('d-m-Y'),
            $starts,
        );
        $paid = array_fill(0, count($starts), 0.0);
        $return = array_fill(0, count($starts), 0.0);

        if ($starts === []) {
            return ['labels' => $labels, 'paid' => $paid, 'return' => $return];
        }

        $boundaries = array_map(fn (Carbon $start) => $start->getTimestamp(), $starts);
        $lastEnd = end($starts)->copy()->$endFormat();

        // Kale rijen, geen modellen: een jaar omzet is dan een paar kolommen per
        // order in plaats van een compleet Order-object met al zijn casts.
        Order::query()
            ->isPaidOrReturn()
            ->where('created_at', '>=', $starts[0])
            ->where('created_at', '<=', $lastEnd)
            ->toBase()
            ->select(['id', 'created_at', 'total', 'status'])
            ->orderBy('id')
            ->chunkById(5000, function ($rows) use (&$paid, &$return, $boundaries) {
                foreach ($rows as $row) {
                    $timestamp = strtotime((string) $row->created_at);
                    if ($timestamp === false) {
                        continue;
                    }

                    $index = self::stepIndexFor($timestamp, $boundaries);
                    if ($index === null) {
                        continue;
                    }

                    if ($row->status === 'return') {
                        $return[$index] += (float) $row->total;
                    } else {
                        $paid[$index] += (float) $row->total;
                    }
                }
            });

        return ['labels' => $labels, 'paid' => $paid, 'return' => $return];
    }

    /** De laatste stap waarvan het begin op of voor het tijdstip ligt (binair zoeken). */
    protected static function stepIndexFor(int $timestamp, array $boundaries): ?int
    {
        $low = 0;
        $high = count($boundaries) - 1;

        if ($high < 0 || $timestamp < $boundaries[0]) {
            return null;
        }

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($boundaries[$middle] <= $timestamp) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low;
    }
}
