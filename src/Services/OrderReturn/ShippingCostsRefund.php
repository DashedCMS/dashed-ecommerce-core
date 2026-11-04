<?php

namespace Dashed\DashedEcommerceCore\Services\OrderReturn;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Verzendkosten terug bij een volledige retour. Bij een herroeping van de hele
 * bestelling krijgt de klant ook de betaalde verzendkosten terug (art. 6:230r
 * BW); de retourverwerking crediteerde tot nu toe alleen de productregels,
 * waardoor een terugbetaling bij de PSP inclusief verzendkosten nergens op
 * paste. Deze klasse zegt of een retour de bestelling leeg maakt en welke
 * verzendregels er dan nog gecrediteerd moeten worden.
 */
class ShippingCostsRefund
{
    public const SKU = 'shipping_costs';

    /**
     * Is er na deze creditering niets meer van de bestelling over?
     *
     * Telt eerdere creditorders mee (de annuleerknop hoogt returned_quantity
     * niet op), zodat een bestelling waarvan een deel al geannuleerd was en
     * de rest nu via een retour terugkomt ook als volledig telt.
     *
     * @param  array<int, int>  $creditQuantities  orderproduct-id => nu te crediteren aantal
     */
    public function isFullReturn(Order $order, array $creditQuantities): bool
    {
        $productLines = $this->lines($order)->filter(fn (OrderProduct $line) => ReturnableLines::isReturnable($line));
        if ($productLines->isEmpty()) {
            return false;
        }

        $credited = $this->creditedProductQuantities($order, $productLines);

        foreach ($productLines as $line) {
            $quantity = (int) $line->quantity;
            if ($quantity < 1) {
                continue;
            }

            $already = max((int) $line->returned_quantity, $credited[$line->id] ?? 0);
            $now = (int) ($creditQuantities[$line->id] ?? 0);

            if ($already + $now < $quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * De verzendregels van de bestelling met het aantal dat nog niet op een
     * creditorder staat, als `refundQuantity` op het model (zoals
     * markAsCancelledWithCredit() het leest). Dit model daarna nooit save()-en.
     *
     * @return Collection<int, OrderProduct>
     */
    public function linesToCredit(Order $order): Collection
    {
        $creditedShipping = (int) abs((int) OrderProduct::query()
            ->whereIn('order_id', $order->creditOrders()->select('id'))
            ->where('sku', self::SKU)
            ->where('price', '<', 0)
            ->sum('quantity'));

        $result = collect();
        foreach ($this->lines($order)->where('sku', self::SKU) as $line) {
            if ((float) $line->price <= 0 || (int) $line->quantity < 1) {
                continue;
            }

            $take = min((int) $line->quantity, $creditedShipping);
            $creditedShipping -= $take;
            $rest = (int) $line->quantity - $take;

            if ($rest > 0) {
                $line->refundQuantity = $rest;
                $result->push($line);
            }
        }

        return $result;
    }

    /** Het bedrag dat linesToCredit() zou crediteren, incl. btw. */
    public function amount(Order $order): float
    {
        return round((float) $this->linesToCredit($order)
            ->sum(fn (OrderProduct $line) => (float) $line->price / max(1, (int) $line->quantity) * (int) $line->refundQuantity), 2);
    }

    /** @return Collection<int, OrderProduct> */
    protected function lines(Order $order): Collection
    {
        // orderProducts() laadt ook verwijderde regels; die tellen niet mee.
        return $order->orderProducts()->get()->reject(fn (OrderProduct $line) => $line->trashed())->values();
    }

    /**
     * Per productregel hoeveel er al op eerdere creditorders staat, gematcht
     * op product en sku zoals FreeShippingReversal dat doet.
     *
     * @return array<int, int>
     */
    protected function creditedProductQuantities(Order $order, Collection $productLines): array
    {
        $key = fn (OrderProduct $line) => ($line->product_id ? $line->product_id . '|' : '')
            . ($line->sku ?: 'name:' . $line->name);

        $pool = [];
        OrderProduct::query()
            ->whereIn('order_id', $order->creditOrders()->select('id'))
            ->where('price', '<', 0)
            ->get()
            ->filter(fn (OrderProduct $line) => ReturnableLines::isReturnable($line))
            ->each(function (OrderProduct $line) use (&$pool, $key) {
                $pool[$key($line)] = ($pool[$key($line)] ?? 0) + abs((int) $line->quantity);
            });

        $result = [];
        foreach ($productLines as $line) {
            $lineKey = $key($line);
            $take = min((int) $line->quantity, $pool[$lineKey] ?? 0);
            if (! $take && $line->product_id) {
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
