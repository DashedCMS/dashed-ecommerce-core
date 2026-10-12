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

        $lines = $order->orderProducts()->with('product')->get();
        if ($lines->contains(fn (OrderProduct $line) => $line->sku === 'shipping_costs')) {
            return null;
        }

        if ($this->alreadyCharged($order)) {
            return null;
        }

        $productLines = $lines->filter(fn (OrderProduct $line) => ReturnableLines::isReturnable($line));

        $orderValue = 0.0;
        $creditNow = 0.0;
        $remainingItems = [];
        foreach ($productLines as $line) {
            $quantity = (int) $line->quantity;
            if ($quantity < 1) {
                continue;
            }
            $unit = (float) $line->price / $quantity;
            $remaining = ReturnableLines::remaining($line);
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
}
