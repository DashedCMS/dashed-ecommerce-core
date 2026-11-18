<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Throwable;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderAddOn;
use Dashed\DashedEcommerceCore\Models\OrderTrackAndTrace;

/**
 * The bond between an add-on order and the parent order it ships with.
 *
 * - link(): the checkout creates the bond right after the add-on order is created.
 * - paid(): add-on paid while the parent is still open -> fulfillment `handled` SILENTLY (no customer mail,
 *   no OrderFulfillmentStatusChangedEvent, so no follow-up flow) and never a label of its own. Parent no
 *   longer open -> the add-on ships separately.
 * - Parent cancelled -> add-ons ship separately; parent replaced ("order wijzigen") -> links move along.
 * - Track & trace on the parent is copied to its linked add-ons (without a customer mail).
 * - Veloyd/MyParcel: no shipment is created for a linked add-on (creating hooks in register()).
 */
final class Linker
{
    /** Statuses in which an add-on must not join the parent's box (anymore). */
    public const PARENT_GONE_STATUSES = ['cancelled', 'return'];

    /** Carrier class => customsetting that switches on automatic pushing after payment. */
    public const CARRIERS = [
        '\Dashed\DashedEcommerceVeloyd\Classes\Veloyd' => 'veloyd_automatically_push_orders',
        '\Dashed\DashedEcommerceMyParcel\Classes\MyParcel' => 'my_parcel_automatically_push_orders',
    ];

    /** Shipment models that must not be created for a linked add-on. */
    public const SHIPMENT_MODELS = [
        '\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder',
        '\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder',
    ];

    /**
     * Model events. Each one is wrapped in rescue(): add-on handling must never block an order save.
     * Model listeners live on the app's event dispatcher, so a new app boot starts clean (no duplicates).
     */
    public static function register(): void
    {
        Order::updated(function (Order $order) {
            rescue(fn () => self::orderUpdated($order), null, true);
        });

        OrderTrackAndTrace::created(function (OrderTrackAndTrace $trackAndTrace) {
            rescue(fn () => self::copyTrackAndTrace($trackAndTrace), null, true);
        });

        foreach (self::SHIPMENT_MODELS as $model) {
            if (! class_exists($model)) {
                continue;
            }

            $model::creating(function ($shipment) {
                return rescue(fn () => self::shipmentAllowed($shipment), true, true) ? null : false;
            });
        }
    }

    public static function orderUpdated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === 'paid') {
            self::paid($order);
        }

        // "Order wijzigen": the add-ons move along to the replacing order.
        if ($order->wasChanged('replaced_by_order_id') && $order->replaced_by_order_id) {
            self::moveToReplacement($order);

            return;
        }

        if ($order->wasChanged('status') && $order->status === 'cancelled' && ! $order->replaced_by_order_id) {
            self::parentCancelled($order);
        }
    }

    /** Link a freshly created order to its parent (status linked). */
    public static function link(Order $addOn, Order $parent): ?OrderAddOn
    {
        if ($addOn->id === $parent->id || $parent->addOnLink()->exists()) {
            return null;
        }

        $link = OrderAddOn::firstOrCreate(
            ['order_id' => $addOn->id],
            ['parent_order_id' => $parent->id, 'status' => OrderAddOn::LINKED],
        );

        $addOn->unsetRelation('addOnLink');
        $parent->unsetRelation('addOnOrders');
        PackingSlip::forget($parent);

        return $link;
    }

    /** The add-on order is paid. */
    public static function paid(Order $addOn): void
    {
        $link = OrderAddOn::where('order_id', $addOn->id)->first();

        if (! $link || $link->paid_at) {
            return;
        }

        $link->update(['paid_at' => now()]);

        if (! $link->isLinked()) {
            return;
        }

        $parent = self::currentParent($link);
        $number = $parent ? Offer::orderNumber($parent) : (string) $link->parent_order_id;

        if (! $parent || ! self::parentIsOpen($parent)) {
            self::makeSeparate($addOn, "bestelling #{$number} was al ingepakt of verzonden toen de betaling binnenkwam");

            return;
        }

        self::markHandledSilently($addOn);
        $link->update(['handled_at' => now()]);

        self::log($addOn->id, "Bijbestelling bij #{$number}: meegestuurd in dezelfde doos, geen eigen verzendlabel.");
        self::log($parent->id, 'Bijbestelling #' . Offer::orderNumber($addOn) . ' gaat mee in deze doos: samen verzenden, één label. De pakbon van deze order toont de extra producten.');
        PackingSlip::forget($parent);

        // A label (and so a track & trace) may already exist for the parent.
        foreach ($parent->trackAndTraces as $trackAndTrace) {
            self::addTrackAndTrace($addOn, $trackAndTrace);
        }
    }

    /**
     * The add-on ships separately after all (at our expense): the parent was already packed/shipped,
     * got cancelled, or the team chose to. From now on it gets a shipment of its own.
     */
    public static function makeSeparate(?Order $addOn, string $reason, bool $prepareShipment = true): void
    {
        $link = $addOn ? OrderAddOn::where('order_id', $addOn->id)->first() : null;

        if (! $link || $link->isSeparate()) {
            return;
        }

        $link->update(['status' => OrderAddOn::SEPARATE, 'reason' => mb_substr($reason, 0, 250)]);
        $addOn->unsetRelation('addOnLink');

        $parent = $link->parentOrder;
        $number = $parent ? Offer::orderNumber($parent) : (string) $link->parent_order_id;

        self::log($addOn->id, "LET OP: deze bijbestelling gaat niet meer mee met #{$number} ({$reason}). Los verzenden, de verzendkosten zijn voor ons.");

        if ($parent) {
            self::log($parent->id, 'Bijbestelling #' . Offer::orderNumber($addOn) . " gaat niet mee in deze doos ({$reason}): die wordt los verzonden.");
            $parent->unsetRelation('addOnOrders');
            PackingSlip::forget($parent);
        }

        PackingSlip::forget($addOn);

        if (! $addOn->isPaidFor()) {
            return;
        }

        // It was handled automatically on payment: back to unhandled so it gets packed and shipped.
        if ($link->handled_at && $addOn->fulfillment_status === 'handled') {
            self::setFulfillmentStatusSilently($addOn, 'unhandled', reopened: true);
        }

        if ($prepareShipment) {
            self::prepareShipment($addOn);
        }

        self::notifyTeam($addOn, $number, $reason);
    }

    public static function moveToReplacement(Order $parent): void
    {
        $replacement = Order::find($parent->replaced_by_order_id);

        if (! $replacement) {
            return;
        }

        $moved = OrderAddOn::where('parent_order_id', $parent->id)->update(['parent_order_id' => $replacement->id]);

        if ($moved) {
            self::log($replacement->id, 'Bijbestelling(en) van #' . Offer::orderNumber($parent) . ' gaan nu mee met deze vervangende bestelling.');
            PackingSlip::forget($parent);
            PackingSlip::forget($replacement);
        }
    }

    public static function parentCancelled(Order $parent): void
    {
        $links = OrderAddOn::where('parent_order_id', $parent->id)
            ->where('status', OrderAddOn::LINKED)
            ->with('order')
            ->get();

        foreach ($links as $link) {
            self::makeSeparate($link->order, 'bestelling #' . Offer::orderNumber($parent) . ' is geannuleerd');
        }
    }

    /** Track & trace added to a parent: the same code on its paid, linked add-ons (no second customer mail). */
    public static function copyTrackAndTrace(OrderTrackAndTrace $trackAndTrace): void
    {
        $links = OrderAddOn::where('parent_order_id', $trackAndTrace->order_id)
            ->where('status', OrderAddOn::LINKED)
            ->whereNotNull('paid_at')
            ->with('order')
            ->get();

        foreach ($links as $link) {
            if ($link->order) {
                self::addTrackAndTrace($link->order, $trackAndTrace);
            }
        }
    }

    /** False for a (non-return) shipment of an add-on that ships with its parent. */
    public static function shipmentAllowed(object $shipment): bool
    {
        if ($shipment->is_return ?? false) {
            return true;
        }

        return ! OrderAddOn::where('order_id', (int) $shipment->order_id)->where('status', OrderAddOn::LINKED)->exists();
    }

    /** The parent can still take the add-on: not cancelled/returned and fulfillment still in an open status. */
    public static function parentIsOpen(Order $parent): bool
    {
        return ! in_array($parent->status, self::PARENT_GONE_STATUSES, true)
            && in_array($parent->fulfillment_status, Settings::openStatuses($parent->site_id), true);
    }

    /** Same conditions as the carriers' own MarkOrderAsPushableListener after payment. */
    public static function prepareShipment(Order $order): void
    {
        if (! $order->street || $order->order_origin === 'pos' || $order->shippingMethod?->sort === 'take_away') {
            return;
        }

        foreach (self::CARRIERS as $carrier => $setting) {
            if (class_exists($carrier) && Customsetting::get($setting, $order->site_id)) {
                rescue(fn () => $carrier::connectOrderWithCarrier($order), null, true);
            }
        }
    }

    /** Follow "order wijzigen" between ordering and paying; stores the current parent on the link. */
    private static function currentParent(OrderAddOn $link): ?Order
    {
        $parent = $link->parentOrder;

        for ($step = 0; $parent && $parent->replaced_by_order_id && $step < 5; $step++) {
            $parent = Order::find($parent->replaced_by_order_id);
        }

        if ($parent && $parent->id !== (int) $link->parent_order_id) {
            $link->update(['parent_order_id' => $parent->id]);
        }

        return $parent;
    }

    private static function markHandledSilently(Order $addOn): void
    {
        self::setFulfillmentStatusSilently($addOn, 'handled');
    }

    /**
     * Deliberately NOT changeFulfillmentStatus(): that mails the customer and dispatches
     * OrderFulfillmentStatusChangedEvent (follow-up/review flows). A plain save() still lets model
     * observers run. A fresh instance is saved, because the given one may be in the middle of its own save.
     */
    private static function setFulfillmentStatusSilently(Order $order, string $status, bool $reopened = false): void
    {
        $fresh = Order::find($order->id);

        if (! $fresh || $fresh->fulfillment_status === $status) {
            return;
        }

        $fresh->fulfillment_status = $status;

        if ($reopened) {
            $fresh->fulfillment_reopened_at = now();
        }

        $fresh->save();

        $order->fulfillment_status = $status;
        $order->syncOriginalAttribute('fulfillment_status');

        if ($reopened) {
            $order->fulfillment_reopened_at = $fresh->fulfillment_reopened_at;
            $order->syncOriginalAttribute('fulfillment_reopened_at');
        }
    }

    private static function addTrackAndTrace(Order $order, OrderTrackAndTrace $trackAndTrace): void
    {
        $order->addTrackAndTrace(
            $trackAndTrace->supplier,
            $trackAndTrace->delivery_company,
            $trackAndTrace->code,
            $trackAndTrace->url,
            $trackAndTrace->expected_delivery_date?->toDateString(),
            false,
        );
    }

    private static function notifyTeam(Order $order, string $parentNumber, string $reason): void
    {
        $center = '\Dashed\DashedMobileApi\Support\NotificationCenter';

        if (! class_exists($center)) {
            return;
        }

        try {
            app($center)->push()
                ->type('order.bijbestelling-los')
                ->title('Bijbestelling los verzenden: #' . Offer::orderNumber($order))
                ->body("Hoorde bij #{$parentNumber}, maar {$reason}. Eigen label nodig.")
                ->route('/order/' . $order->id)
                ->data(['type' => 'admin', 'key' => 'order.bijbestelling-los', 'order_id' => $order->id])
                ->send();
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function log(int $orderId, string $note): void
    {
        rescue(fn () => OrderLog::createLog(orderId: $orderId, tag: 'system.note.created', note: $note, isSystem: true), null, true);
    }
}
