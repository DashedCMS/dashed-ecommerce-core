<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Throwable;
use Illuminate\Support\Carbon;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Classes\SKUs;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

/**
 * Gratis verzending is een voorwaardelijke korting. Zakt een gratis verzonden
 * bestelling door een deelretour of deelannulering onder de drempel, dan mag
 * het normale verzendtarief met de terugbetaling verrekend worden. Deze klasse
 * rekent alleen uit of en hoeveel; de creditregel zelf maakt
 * Order::markAsCancelledWithCredit().
 */
class FreeShippingReversal
{
    public const SKU = 'shipping_costs_reversal';

    /**
     * @param  array<int, int>  $creditQuantities  aantal dat nu gecrediteerd wordt per order_product_id
     */
    public function propose(Order $order, array $creditQuantities): ?FreeShippingReversalProposal
    {
        try {
            return $this->calculate($order, $creditQuantities);
        } catch (Throwable $e) {
            // Een retour mag nooit stuklopen op het voorstel.
            report($e);

            return null;
        }
    }

    public function alreadyCharged(Order $order): bool
    {
        return OrderProduct::query()
            ->whereIn('order_id', $order->creditOrders()->select('id'))
            ->where('sku', self::SKU)
            ->exists();
    }

    protected function calculate(Order $order, array $creditQuantities): ?FreeShippingReversalProposal
    {
        if (! Customsetting::get('returns_charge_shipping_below_threshold', null, false)) {
            return null;
        }

        $from = Customsetting::get('returns_charge_shipping_from');
        if (! $from || ! $order->created_at || $order->created_at->lt(Carbon::parse($from)->startOfDay())) {
            return null;
        }

        $method = $order->shippingMethod;
        if (! $method || $method->sort !== 'free_delivery') {
            return null;
        }

        // orderProducts() laadt ook verwijderde regels; die tellen niet mee.
        $lines = $order->orderProducts()->with('product')->get()
            ->reject(fn (OrderProduct $line) => $line->trashed());
        if ($lines->contains(fn (OrderProduct $line) => $line->sku === 'shipping_costs')) {
            return null;
        }

        if ($this->alreadyCharged($order)) {
            return null;
        }

        $productLines = $lines->filter(fn (OrderProduct $line) => ReturnableLines::isReturnable($line));

        $credited = $this->creditedQuantities($order, $productLines);

        $orderValue = 0.0;
        $creditNow = 0.0;
        $remainingItems = [];
        foreach ($productLines as $line) {
            $quantity = (int) $line->quantity;
            if ($quantity < 1) {
                continue;
            }
            $unit = (float) $line->price / $quantity;
            // De annuleerknop hoogt returned_quantity niet op: tel de eerdere
            // creditorders mee voor wat er nog over is.
            $already = max((int) $line->returned_quantity, $credited[$line->id] ?? 0);
            $remaining = max(0, $quantity - $already);
            $now = max(0, min((int) ($creditQuantities[$line->id] ?? 0), $remaining));

            $orderValue += (float) $line->price;
            $creditNow += $unit * $now;

            if ($remaining - $now > 0) {
                $remainingItems[] = (object) ['model' => $line->product, 'qty' => $remaining - $now];
            }
        }

        if ($creditNow <= 0) {
            return null;
        }

        // Eerdere credits komen uit de creditorders en niet uit
        // returned_quantity: de annuleerknop hoogt dat veld niet op.
        $valueBefore = round($orderValue - $this->creditedProductValue($order, $productLines->pluck('sku')->filter()->all()), 2);
        $valueAfter = round($valueBefore - $creditNow, 2);

        if ($valueAfter <= 0) {
            return null;
        }

        $threshold = (float) ($order->free_shipping_threshold ?? $method->minimum_order_value);
        if ($threshold <= 0 || $valueAfter >= $threshold) {
            return null;
        }

        $country = $order->country ?: $order->invoice_country;
        if (! $country) {
            return null;
        }

        $methods = ShoppingCart::getAvailableShippingMethods(
            $country,
            '',
            null,
            ShoppingCart::shippingOrderValue($valueAfter, $remainingItems),
            $remainingItems,
        );

        $paid = collect($methods)
            ->filter(fn ($candidate) => ! in_array($candidate->sort, ['free_delivery', 'take_away'], true) && (float) $candidate->costs > 0)
            ->sortBy(fn ($candidate) => (float) $candidate->costs)
            ->first();

        if (! $paid) {
            return null;
        }

        $amount = round(min((float) $paid->costs, $creditNow), 2);
        if ($amount <= 0) {
            return null;
        }

        return new FreeShippingReversalProposal(
            amount: $amount,
            threshold: $threshold,
            valueBefore: $valueBefore,
            valueAfter: $valueAfter,
            methodName: (string) ($paid->correctName ?? $paid->name),
        );
    }

    /**
     * Wat er op eerdere creditorders al aan producten is gecrediteerd. Losse
     * extra regels (eigen omschrijving, geen product) tellen niet mee.
     *
     * @param  array<int, string>  $productSkus
     */
    protected function creditedProductValue(Order $order, array $productSkus): float
    {
        $excluded = array_merge(SKUs::nonReturnable(), [self::SKU]);

        return (float) OrderProduct::query()
            ->whereIn('order_id', $order->creditOrders()->select('id'))
            ->whereNotIn('sku', $excluded)
            ->where('price', '<', 0)
            ->where(fn ($query) => $query->whereNotNull('product_id')->orWhereIn('sku', $productSkus))
            ->get()
            ->sum(fn (OrderProduct $line) => abs((float) $line->price));
    }

    /**
     * Gecrediteerd aantal per originele regel volgens de creditorders.
     * Koppelt op product_id + sku, anders sku, anders naam, en verdeelt over
     * regels met dezelfde sleutel zonder het regelaantal te overschrijden.
     *
     * @param  \Illuminate\Support\Collection<int, OrderProduct>  $productLines
     * @return array<int, int> order_product_id => aantal
     */
    protected function creditedQuantities(Order $order, $productLines): array
    {
        $excluded = array_merge(SKUs::nonReturnable(), [self::SKU]);

        $key = fn (OrderProduct $line) => ($line->product_id ? $line->product_id . '|' : '')
            . ($line->sku ?: 'name:' . $line->name);

        $pool = [];
        OrderProduct::query()
            ->whereIn('order_id', $order->creditOrders()->select('id'))
            ->where('price', '<', 0)
            ->get()
            ->reject(fn (OrderProduct $line) => in_array($line->sku, $excluded, true))
            ->each(function (OrderProduct $line) use (&$pool, $key) {
                $pool[$key($line)] = ($pool[$key($line)] ?? 0) + abs((int) $line->quantity);
            });

        $result = [];
        foreach ($productLines as $line) {
            $lineKey = $key($line);
            $take = min((int) $line->quantity, $pool[$lineKey] ?? 0);
            if (! $take && $line->product_id) {
                // Creditregel zonder product_id: val terug op sku/naam.
                $fallback = $line->sku ?: 'name:' . $line->name;
                $take = min((int) $line->quantity, $pool[$fallback] ?? 0);
                $lineKey = $fallback;
            }
            if ($take > 0) {
                $pool[$lineKey] -= $take;
                $result[$line->id] = $take;
            }
        }

        return $result;
    }
}
