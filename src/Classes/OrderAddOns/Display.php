<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Classes\SKUs;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Shared presentation of add-on orders ("bijbestellingen") for the CMS order view,
 * the order list, the packing slip and the mobile API. Read-only: linking and
 * separating happen elsewhere (Linker).
 */
final class Display
{
    private static ?bool $installed = null;

    /**
     * Is the core migration run (dashed__order_add_ons exists)? Cached per process, so sites that did not
     * migrate yet keep a working order list, view and packing slip instead of a SQL error.
     */
    public static function installed(): bool
    {
        if (self::$installed === null) {
            try {
                self::$installed = Schema::hasTable('dashed__order_add_ons');
            } catch (Throwable $e) {
                return false;
            }
        }

        return self::$installed;
    }

    /** Relations for the mobile API detail, or none while the table is missing. */
    public static function detailRelations(): array
    {
        return self::installed() ? ['addOnLink.parentOrder:id,invoice_id', 'addOnOrders.orderProducts'] : [];
    }

    /** Eager loads for order lists (Filament table, mobile API index): no N+1 per row. */
    public static function eagerLoadForList(Builder $query): Builder
    {
        if (! self::installed()) {
            return $query;
        }

        return $query
            ->withCount(['addOnOrders as add_on_orders_count'])
            ->with(['addOnLink.parentOrder:id,invoice_id']);
    }

    /** The number shown to people: invoice number, or the id while there is none. */
    public static function number(Order $order): string
    {
        return (string) ($order->invoice_id ?: $order->id);
    }

    /**
     * Linked add-on orders of a parent, with their product lines.
     *
     * @return Collection<int, Order>
     */
    public static function linkedAddOns(Order $parent): Collection
    {
        if (! self::installed()) {
            return collect();
        }

        if (! $parent->relationLoaded('addOnOrders')) {
            $parent->load('addOnOrders.orderProducts');
        }

        return $parent->addOnOrders->sortBy('id')->values();
    }

    /** Number of linked add-ons; uses the withCount column when the list query loaded it. */
    public static function linkedAddOnsCount(Order $parent): int
    {
        if (array_key_exists('add_on_orders_count', $parent->getAttributes())) {
            return (int) $parent->getAttribute('add_on_orders_count');
        }

        if ($parent->relationLoaded('addOnOrders')) {
            return $parent->addOnOrders->count();
        }

        if (! self::installed()) {
            return 0;
        }

        // Same rule as Order::addOnOrders(): linked, paid and not cancelled/returned.
        return $parent->addOnOrders()->count();
    }

    /**
     * Lines that physically go in the box: everything except shipping/payment cost lines.
     *
     * @return Collection<int, \Dashed\DashedEcommerceCore\Models\OrderProduct>
     */
    public static function boxLines(Order $order): Collection
    {
        return $order->orderProducts
            ->reject(fn ($line) => in_array($line->sku, [...SKUs::hideOnPackingSlip(), 'product_costs'], true))
            ->values();
    }

    public static function itemsCount(Order $order): int
    {
        return (int) self::boxLines($order)->sum('quantity');
    }

    /** "Kleur: Zand, Maat: L" from the chosen product extras of a line. */
    public static function options($line): string
    {
        return collect(is_array($line->product_extras) ? $line->product_extras : [])
            ->filter(fn ($option) => is_array($option) && filled($option['name'] ?? null))
            ->map(fn ($option) => $option['name'] . ': ' . (is_scalar($option['value'] ?? null) ? $option['value'] : ''))
            ->implode(', ');
    }

    /**
     * Order list badge: "+ bijbestelling" on a parent, "Bijbestelling bij #X" on an add-on.
     *
     * @return array{label: string, color: string}|null
     */
    public static function listBadge(Order $order): ?array
    {
        if (! self::installed()) {
            return null;
        }

        $link = $order->addOnLink;

        if ($link) {
            $number = $link->parentOrder ? self::number($link->parentOrder) : (string) $link->parent_order_id;

            return $link->isLinked()
                ? ['label' => __('Bijbestelling bij #:nummer', ['nummer' => $number]), 'color' => 'warning']
                : ['label' => __('Bijbestelling (los), hoorde bij #:nummer', ['nummer' => $number]), 'color' => 'gray'];
        }

        $count = self::linkedAddOnsCount($order);

        if ($count > 0) {
            return ['label' => $count > 1 ? __('+ :aantal bijbestellingen', ['aantal' => $count]) : __('+ bijbestelling'), 'color' => 'warning'];
        }

        return null;
    }

    /**
     * Mobile API block. Detail: full add-on orders with products; summary: only the count.
     *
     * @return array{add_on_orders?: array, add_on_orders_count?: int, add_on_for: ?array}
     */
    public static function apiPayload(Order $order, bool $detail): array
    {
        if (! self::installed()) {
            return $detail ? ['add_on_orders' => [], 'add_on_for' => null] : ['add_on_orders_count' => 0, 'add_on_for' => null];
        }

        $link = $order->addOnLink;

        $payload = $detail
            ? ['add_on_orders' => self::linkedAddOns($order)->map(fn (Order $addOn) => [
                'id' => $addOn->id,
                'invoice_id' => $addOn->invoice_id,
                'items_count' => self::itemsCount($addOn),
                'products' => self::boxLines($addOn)->map(fn ($line) => [
                    'name' => (string) $line->name,
                    'quantity' => (int) $line->quantity,
                    'sku' => $line->sku,
                ])->values()->all(),
            ])->all()]
            : ['add_on_orders_count' => self::linkedAddOnsCount($order)];

        $payload['add_on_for'] = $link ? [
            'id' => (int) $link->parent_order_id,
            'invoice_id' => $link->parentOrder?->invoice_id,
            'status' => $link->status,
        ] : null;

        return $payload;
    }
}
