<?php

namespace Dashed\DashedEcommerceCore\Classes\Satisfaction;

use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Satisfaction\Satisfaction;
use Dashed\DashedEcommerceCore\Mail\DeliverySatisfactionMail;

/**
 * De tevredenheidsvraag na levering. Een bestelling komt in aanmerking als hij
 * betaald is, op afgehandeld staat en dat precies het ingestelde aantal dagen
 * geleden werd (met drie dagen speling voor een gemiste nacht), een
 * e-mailadres heeft, nog geen vraag kreeg, en niet van Bol komt: die klant is
 * klant van Bol, Bol praat met hem.
 *
 * Het moment van afhandelen komt uit de orderlog
 * (`order.changed-fulfillment-status-to-handled`), want een kolom daarvoor is
 * er niet.
 */
class DeliverySatisfaction
{
    public const HANDLED_TAG = 'order.changed-fulfillment-status-to-handled';

    public const GRACE_DAYS = 3;

    public static function dueOrders(string $siteId): Builder
    {
        $days = Satisfaction::deliveryDays($siteId);
        $until = now()->subDays($days);
        $since = $until->copy()->subDays(self::GRACE_DAYS);

        return Order::query()
            ->where('site_id', $siteId)
            ->isPaid()
            ->where('fulfillment_status', 'handled')
            ->whereNull('credit_for_order_id')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(fn ($q) => $q->whereNull('order_origin')->orWhere('order_origin', '!=', 'Bol'))
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('dashed__order_logs')
                ->whereColumn('dashed__order_logs.order_id', 'dashed__orders.id')
                ->where('dashed__order_logs.tag', self::HANDLED_TAG)
                ->whereBetween('dashed__order_logs.created_at', [$since, $until]))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('dashed__satisfaction_ratings')
                ->where('dashed__satisfaction_ratings.source', Satisfaction::SOURCE_DELIVERY)
                ->whereColumn('dashed__satisfaction_ratings.order_id', 'dashed__orders.id'));
    }

    public static function send(Order $order): bool
    {
        if ($order->order_origin === 'Bol' || blank($order->email)) {
            return false;
        }

        $rating = Satisfaction::request(Satisfaction::SOURCE_DELIVERY, $order, [
            'site_id' => $order->site_id,
            'order_id' => $order->id,
            'email' => $order->email,
            'locale' => $order->locale,
        ]);

        if ($rating->isRated()) {
            return false;
        }

        Mail::to($order->email)
            ->locale($order->locale ?: app()->getLocale())
            ->send(new DeliverySatisfactionMail($order, $rating));

        return true;
    }
}
