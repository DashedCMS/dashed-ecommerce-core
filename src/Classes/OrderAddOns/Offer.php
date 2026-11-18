<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderAddOn;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;

/**
 * Of een order nog een bijbestelling mag krijgen die zonder verzendkosten in dezelfde doos meegaat.
 * Open zolang: bijbestellen staat aan voor de site, betaalde webshoporder (geen marktplaats/kassa), geen
 * creditorder, niet vervangen, niet zelf een bijbestelling, fulfilmentstatus nog in de open statussen,
 * verzending als pakket (niet ophalen, niet alleen digitaal), de zone heeft een gratis verzendmethode
 * en binnen het maximum aantal uur na betaling (als dat ingesteld is). Daarna nog de app-regels
 * (OrderAddOns::rejectOrderUsing()).
 */
final class Offer
{
    public const ROUTE = 'dashed.frontend.order-add-ons.show';

    public const REASON_DISABLED = 'disabled';

    public const REASON_NOT_OWN_ORDER = 'not_own_order';

    public const REASON_NOT_PAID = 'not_paid';

    public const REASON_CREDIT_ORDER = 'credit_order';

    public const REASON_REPLACED = 'replaced';

    public const REASON_IS_ADD_ON = 'is_add_on';

    public const REASON_STATUS_CLOSED = 'status_closed';

    public const REASON_TAKE_AWAY = 'take_away';

    public const REASON_DIGITAL_ONLY = 'digital_only';

    public const REASON_NO_FREE_SHIPPING_METHOD = 'no_free_shipping_method';

    public const REASON_EXPIRED = 'expired';

    public static function isOpen(Order $order): bool
    {
        return self::reason($order) === null;
    }

    /**
     * Waarom het aanbod dicht is, of null als het open is.
     *
     * @param  string  $context  `offer` of `mail`, doorgegeven aan de app-regels
     */
    public static function reason(Order $order, string $context = 'offer'): ?string
    {
        if (! Settings::enabled($order->site_id)) {
            return self::REASON_DISABLED;
        }

        if (($order->order_origin ?: 'own') !== 'own') {
            return self::REASON_NOT_OWN_ORDER;
        }

        if ($order->status !== 'paid') {
            return self::REASON_NOT_PAID;
        }

        if ($order->credit_for_order_id || (float) $order->total < 0) {
            return self::REASON_CREDIT_ORDER;
        }

        if ($order->replaced_by_order_id) {
            return self::REASON_REPLACED;
        }

        if (OrderAddOn::query()->where('order_id', $order->id)->exists()) {
            return self::REASON_IS_ADD_ON;
        }

        if (! in_array($order->fulfillment_status, Settings::openStatuses($order->site_id), true)) {
            return self::REASON_STATUS_CLOSED;
        }

        $shippingMethod = $order->shippingMethod;

        if (! $shippingMethod || $shippingMethod->sort === 'take_away' || blank($order->street)) {
            return self::REASON_TAKE_AWAY;
        }

        $lines = $order->orderProducts()->whereNotNull('product_id')->with('product.productGroup')->get();

        if ($lines->isEmpty() || $lines->every(fn ($line) => ! $line->product || $line->product->isDigital())) {
            return self::REASON_DIGITAL_ONLY;
        }

        if (! self::freeShippingMethod($order)) {
            return self::REASON_NO_FREE_SHIPPING_METHOD;
        }

        $deadline = self::deadline($order);

        if ($deadline && $deadline->isPast()) {
            return self::REASON_EXPIRED;
        }

        return OrderAddOns::orderRejection($order, $context);
    }

    /** Tot wanneer het aanbod open is; null als alleen de fulfilmentstatus het sluit (max uren = 0). */
    public static function deadline(Order $order): ?Carbon
    {
        $hours = Settings::maxHours($order->site_id);

        if ($hours <= 0) {
            return null;
        }

        return self::paidAt($order)->addHours($hours);
    }

    /** Het betaalmoment: de laatste betaalde betaling, anders het aanmaakmoment van de order. */
    public static function paidAt(Order $order): Carbon
    {
        $paidAt = $order->orderPayments()->where('status', 'paid')->max('updated_at');

        return Carbon::parse($paidAt ?: $order->created_at);
    }

    /** "tot je bestelling wordt ingepakt", "tot vandaag 14:00", "tot morgen 14:00" of "tot dinsdag 14:00". */
    public static function deadlineText(Order $order, ?string $locale = null): string
    {
        $deadline = self::deadline($order);

        if (! $deadline) {
            return Translation::get('deadline-until-packed', 'bijbestellen', 'tot je bestelling wordt ingepakt');
        }

        $deadline = $deadline->copy()->setTimezone(config('app.timezone'))->locale($locale ?: app()->getLocale());

        if ($deadline->isToday()) {
            $moment = Translation::get('deadline-today', 'bijbestellen', 'vandaag').' '.$deadline->format('H:i');
        } elseif ($deadline->isTomorrow()) {
            $moment = Translation::get('deadline-tomorrow', 'bijbestellen', 'morgen').' '.$deadline->format('H:i');
        } elseif ($deadline->lte(now()->addDays(6)->endOfDay())) {
            $moment = $deadline->translatedFormat('l H:i');
        } else {
            $moment = $deadline->translatedFormat('j F H:i');
        }

        return str_replace(':moment:', $moment, Translation::get('deadline-until', 'bijbestellen', 'tot :moment:'));
    }

    /**
     * "tot morgen 14:00, of tot we je bestelling inpakken" als er een deadline is, anders
     * "tot je bestelling wordt ingepakt": de volledige zinsnede voor teksten als "Dat kan :deadline:.".
     */
    public static function untilText(Order $order, ?string $locale = null): string
    {
        $text = self::deadlineText($order, $locale);

        if (! self::deadline($order)) {
            return $text;
        }

        return str_replace(':deadline:', $text, Translation::get('deadline-or-until-packed', 'bijbestellen', ':deadline:, of tot we je bestelling inpakken'));
    }

    /**
     * De ondertekende link naar de bijbestelpagina. Verloopt bewust niet: een gesloten aanbod
     * toont op de pagina zelf een vriendelijke "dicht"-melding in plaats van een 403.
     *
     * @param  array<string, mixed>  $parameters  extra queryparameters (bijv. `via`)
     */
    public static function url(Order $order, array $parameters = []): string
    {
        return URL::signedRoute(self::ROUTE, ['order' => $order->id] + $parameters);
    }

    /** De methode "gratis verzending" in de zone van het afleveradres: daarmee kost de bijbestelling € 0 verzending. */
    public static function freeShippingMethod(Order $order): ?ShippingMethod
    {
        $zone = rescue(fn () => ShoppingCart::getShippingZoneByCountry((string) $order->country), null, false);

        if (! $zone) {
            return null;
        }

        return ShippingMethod::query()
            ->where('shipping_zone_id', $zone->id)
            ->where('sort', 'free_delivery')
            ->orderBy('minimum_order_value')
            ->first();
    }

    /** Het nummer dat de klant kent: het factuurnummer, anders het order-id. */
    public static function orderNumber(Order $order): string
    {
        return (string) ($order->invoice_id ?: $order->id);
    }
}
