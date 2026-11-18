<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;

/**
 * Add-on orders on the packing slip: the parent lists the add-on lines (so they
 * get packed in the same box), the add-on itself says it ships with the parent.
 */
final class PackingSlip
{
    /**
     * Delete the cached packing-slip PDF so the next download renders it again.
     * Call this for the parent (and the add-on) whenever an add-on is linked or separated.
     */
    public static function forget(?Order $order): void
    {
        if (! $order) {
            return;
        }

        try {
            $path = $order->packingSlipPath();

            if ($path && Storage::disk('dashed')->exists($path)) {
                Storage::disk('dashed')->delete($path);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Note at the top of the packing slip, or null when the order has nothing to do with add-ons. */
    public static function notice(Order $order): ?string
    {
        if (! Display::installed()) {
            return null;
        }

        $link = rescue(fn () => $order->addOnLink, null, true);

        if ($link?->goesAlong()) {
            return Translation::get('packing-slip-add-on-notice', 'bijbestellen', 'BIJBESTELLING bij #:nummer:: samen verzenden, één doos (geen eigen label).', 'text', [
                'nummer' => $link->parentOrder ? Display::number($link->parentOrder) : $link->parent_order_id,
            ]);
        }

        $numbers = Display::linkedAddOns($order)->map(fn (Order $addOn) => '#' . Display::number($addOn));

        if ($numbers->isEmpty()) {
            return null;
        }

        return Translation::get('packing-slip-parent-notice', 'bijbestellen', "LET OP: bijbestelling :nummers: gaat mee in deze doos (regels met '+ Bijbestelling').", 'text', [
            'nummers' => $numbers->implode(', '),
        ]);
    }

    /**
     * The add-on lines to add to the parent's packing slip.
     *
     * @return Collection<int, array{prefix: string, line: \Dashed\DashedEcommerceCore\Models\OrderProduct}>
     */
    public static function addOnLines(Order $parent): Collection
    {
        if (! Display::installed()) {
            return collect();
        }

        if ($parent->addOnLink?->isLinked()) {
            return collect();
        }

        return Display::linkedAddOns($parent)->flatMap(function (Order $addOn) {
            $prefix = Translation::get('packing-slip-add-on-line-prefix', 'bijbestellen', '+ Bijbestelling #:nummer:', 'text', [
                'nummer' => Display::number($addOn),
            ]);

            return Display::boxLines($addOn)->map(fn ($line) => ['prefix' => $prefix, 'line' => $line]);
        })->values();
    }
}
