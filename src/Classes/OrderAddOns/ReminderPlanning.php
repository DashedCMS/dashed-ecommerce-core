<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Illuminate\Support\Carbon;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderAddOn;
use Dashed\DashedEcommerceCore\Models\OrderAddOnOffer;
use Dashed\DashedEcommerceCore\Mail\OrderAddOnReminderMail;
use Dashed\DashedEcommerceCore\Jobs\SendOrderAddOnReminderJob;

/**
 * De herinneringsmail "Nog iets vergeten?": één keer per order, Settings::mailDelayMinutes() na betaling,
 * alleen als de ingang `reminder_mail` aanstaat, het aanbod dan nog open is (ook volgens de app-regels met
 * context `mail`), er nog niets is bijbesteld, er iets voor te stellen is en hetzelfde adres binnen de
 * rustperiode niet al zo'n mail kreeg. plan() draait elke vijf minuten (dashed:order-add-ons-mails) en zet
 * per order een job op de wachtrij; send() controleert alles opnieuw en legt een overgeslagen mail vast
 * met de reden (`mail_skipped`).
 */
final class ReminderPlanning
{
    public const SENT = 'sent';

    public const SKIP_NO_ORDER = 'no_order';

    public const SKIP_ALREADY_PROCESSED = 'already_processed';

    public const SKIP_ENTRY_POINT_OFF = 'entry_point_off';

    public const SKIP_OFFER_CLOSED = 'offer_closed';

    public const SKIP_ALREADY_ADDED = 'already_added';

    public const SKIP_RECENTLY_MAILED = 'recently_mailed';

    public const SKIP_NO_SUGGESTIONS = 'no_suggestions';

    public const SUGGESTIONS = 3;

    /** Zonder maximum aantal uur: hoelang na het mailmoment een order nog een herinnering kan krijgen. */
    public const WINDOW_HOURS_WITHOUT_MAX = 24;

    /** @return int aantal jobs op de wachtrij gezet */
    public function plan(): int
    {
        $windows = $this->windows();

        if ($windows === []) {
            return 0;
        }

        $earliest = collect($windows)->min(fn (array $window) => $window['from']->getTimestamp());
        $planned = 0;

        Order::query()
            ->where('status', 'paid')
            ->where(fn ($query) => $query->where('order_origin', 'own')->orWhereNull('order_origin'))
            ->whereNull('credit_for_order_id')
            ->whereNotNull('email')
            ->where('created_at', '>=', Carbon::createFromTimestamp($earliest)->subDay())
            ->whereNotIn('id', OrderAddOnOffer::query()
                ->where(fn ($query) => $query->whereNotNull('mail_sent_at')->orWhereNotNull('mail_skipped'))
                ->select('order_id'))
            ->orderBy('id')
            ->each(function (Order $order) use ($windows, &$planned) {
                $window = $windows[(string) $order->site_id] ?? (count($windows) === 1 && blank($order->site_id) ? reset($windows) : null);

                if (! $window) {
                    return;
                }

                $paidAt = Offer::paidAt($order);

                if ($paidAt->lt($window['from']) || $paidAt->gt($window['until'])) {
                    return;
                }

                SendOrderAddOnReminderJob::dispatch($order->id);
                $planned++;
            });

        return $planned;
    }

    /** @return string `sent` of de reden waarom niet (ook vastgelegd in `mail_skipped`) */
    public function send(int $orderId): string
    {
        $order = Order::find($orderId);

        if (! $order || blank($order->email)) {
            return self::SKIP_NO_ORDER;
        }

        $offer = OrderAddOnOffer::for($order);

        if ($offer->mail_sent_at || $offer->mail_skipped) {
            return self::SKIP_ALREADY_PROCESSED;
        }

        $reason = $this->reasonNotToSend($order);
        $suggestions = $reason ? collect() : Suggestions::for($order, self::SUGGESTIONS);

        if (! $reason && $suggestions->isEmpty()) {
            $reason = self::SKIP_NO_SUGGESTIONS;
        }

        if ($reason) {
            $this->claim($offer, ['mail_skipped' => mb_substr($reason, 0, 191)]);

            return $reason;
        }

        $locale = $order->locale ?: config('app.locale');

        // Eerst atomair vastleggen: alleen wie de rij claimt verstuurt; een tweede job tegelijk verstuurt niets.
        if (! $this->claim($offer, ['mail_sent_at' => now()])) {
            return self::SKIP_ALREADY_PROCESSED;
        }

        Mail::to($order->email)->send(
            (new OrderAddOnReminderMail($order, $suggestions->pluck('id')->map(fn ($id) => (int) $id)->all(), Offer::untilText($order, $locale)))->locale($locale)
        );

        return self::SENT;
    }

    /** Zet de waarden alleen als de mail nog niet verstuurd of overgeslagen is; true als deze aanroep won. */
    private function claim(OrderAddOnOffer $offer, array $values): bool
    {
        return OrderAddOnOffer::query()
            ->whereKey($offer->id)
            ->whereNull('mail_sent_at')
            ->whereNull('mail_skipped')
            ->update(array_merge($values, ['updated_at' => now()])) === 1;
    }

    private function reasonNotToSend(Order $order): ?string
    {
        if (! Settings::entryPointEnabled(Settings::ENTRY_REMINDER_MAIL, $order->site_id)) {
            return self::SKIP_ENTRY_POINT_OFF;
        }

        if ($closed = Offer::reason($order, 'mail')) {
            return self::SKIP_OFFER_CLOSED.':'.$closed;
        }

        if (OrderAddOn::query()->where('parent_order_id', $order->id)->exists()) {
            return self::SKIP_ALREADY_ADDED;
        }

        $cooldownDays = Settings::mailCooldownDays($order->site_id);

        if ($cooldownDays > 0) {
            $recent = OrderAddOnOffer::query()
                ->where('email', mb_strtolower(trim((string) $order->email)))
                ->where('order_id', '!=', $order->id)
                ->where('mail_sent_at', '>=', now()->subDays($cooldownDays))
                ->exists();

            if ($recent) {
                return self::SKIP_RECENTLY_MAILED;
            }
        }

        return null;
    }

    /**
     * Per site met bijbestellen en de herinneringsmail aan: het betaalvenster waarin een order nu aan de
     * beurt is (van: begin van het aanbod, tot: nu min de wachttijd).
     *
     * @return array<string, array{from: Carbon, until: Carbon}>
     */
    private function windows(): array
    {
        $windows = [];

        foreach (Sites::getSites() as $site) {
            $siteId = (string) ($site['id'] ?? '');

            if (! Settings::enabled($siteId) || ! Settings::entryPointEnabled(Settings::ENTRY_REMINDER_MAIL, $siteId)) {
                continue;
            }

            $delay = Settings::mailDelayMinutes($siteId);
            $maxHours = Settings::maxHours($siteId);
            $until = now()->subMinutes($delay);
            $from = $maxHours > 0
                ? now()->subHours($maxHours)
                : $until->copy()->subHours(self::WINDOW_HOURS_WITHOUT_MAX);

            if ($from->lt($until)) {
                $windows[$siteId] = ['from' => $from, 'until' => $until];
            }
        }

        return $windows;
    }
}
