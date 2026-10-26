<?php

namespace Dashed\DashedEcommerceCore\Classes\Alerts;

use Illuminate\Support\Carbon;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Mail\NotificationMail;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification as FilamentNotification;

/**
 * "Er komt niets binnen": een paar uur zonder betaalde bestelling op een
 * shop die rond die tijd normaal wel verkoopt, betekent meestal een kapotte
 * checkout of betaalprovider en niet een rustige middag.
 *
 * Het venster is drie uur en niet één: per uur is het aantal bestellingen te
 * grillig om er een alarm op te zetten. Het verwachte aantal is het gemiddelde
 * van hetzelfde venster op dezelfde weekdag in de vier weken ervoor. Alarm
 * alleen bij minstens drie verwachte bestellingen en minder dan een kwart
 * daarvan, zodat een stille shop of een stil uur geen vals alarm geeft. Alleen
 * eigen bestellingen tellen (webshop en kassa), want Bol en andere kanalen
 * zeggen niets over de eigen checkout.
 */
class RevenueAlarm
{
    public const WINDOW_HOURS = 3;

    public const WEEKS = 4;

    public const MIN_EXPECTED = 3;

    public const THRESHOLD = 0.25;

    public const THROTTLE_HOURS = 6;

    /**
     * @return array{expected: float, actual: int, unpaid: int, alarm: bool, last_paid_at: ?Carbon}
     */
    public static function evaluate(string $siteId, ?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy();
        $from = $now->copy()->subHours(self::WINDOW_HOURS);

        $actual = self::paidOrders($siteId, $from, $now)->count();

        $total = 0;
        for ($week = 1; $week <= self::WEEKS; $week++) {
            $total += self::paidOrders($siteId, $from->copy()->subWeeks($week), $now->copy()->subWeeks($week))->count();
        }
        $expected = $total / self::WEEKS;

        $unpaid = self::ownOrders($siteId)
            ->whereBetween('created_at', [$from, $now])
            ->whereIn('status', ['pending', 'cancelled'])
            ->count();

        $lastPaid = self::ownOrders($siteId)
            ->isPaid()
            ->where('created_at', '<=', $now)
            ->max('created_at');

        return [
            'expected' => round($expected, 1),
            'actual' => $actual,
            'unpaid' => $unpaid,
            'alarm' => $expected >= self::MIN_EXPECTED && $actual < $expected * self::THRESHOLD,
            'last_paid_at' => $lastPaid ? Carbon::parse($lastPaid) : null,
        ];
    }

    /**
     * Loopt alle sites af en meldt een alarm, hooguit één keer per zes uur per
     * site. Geeft het aantal verstuurde alarmen terug.
     */
    public static function check(?Carbon $now = null): int
    {
        $sent = 0;

        foreach (Sites::getSites() as $site) {
            $siteId = (string) $site['id'];

            if (! self::enabled($siteId)) {
                continue;
            }

            $result = self::evaluate($siteId, $now);

            if (! $result['alarm']) {
                continue;
            }

            if (! Cache::add('dashed:revenue-alarm:'.$siteId, true, now()->addHours(self::THROTTLE_HOURS))) {
                continue;
            }

            self::notify($site, $result);
            $sent++;
        }

        return $sent;
    }

    public static function enabled(string $siteId): bool
    {
        return (bool) Customsetting::get('revenue_alarm_enabled', $siteId, true);
    }

    /**
     * @param  array{expected: float, actual: int, unpaid: int, alarm: bool, last_paid_at: ?Carbon}  $result
     */
    protected static function notify(array $site, array $result): void
    {
        $title = __('Er komen geen bestellingen binnen op :site', ['site' => $site['name'] ?? $site['id']]);

        $lines = [
            __('In de afgelopen :uren uur zijn er :actual betaalde bestellingen binnengekomen. Rond deze tijd zijn dat er normaal :expected.', [
                'uren' => self::WINDOW_HOURS,
                'actual' => $result['actual'],
                'expected' => $result['expected'],
            ]),
            $result['last_paid_at']
                ? __('De laatste betaalde bestelling was :tijd.', ['tijd' => $result['last_paid_at']->diffForHumans()])
                : __('Er is nog nooit een betaalde bestelling binnengekomen.'),
        ];

        if ($result['unpaid'] > 0) {
            $lines[] = __('In dezelfde periode zijn :aantal bestellingen aangemaakt die niet betaald zijn. Controleer de betaalprovider.', ['aantal' => $result['unpaid']]);
        } else {
            $lines[] = __('Er zijn in die periode ook geen onbetaalde bestellingen aangemaakt. Controleer of de checkout werkt.');
        }

        $body = implode("\n\n", $lines);

        foreach ((array) Customsetting::get('notification_invoice_emails', $site['id'], []) as $email) {
            rescue(fn () => Mail::to($email)->send(new NotificationMail(implode(' ', $lines), $title)), report: false);
        }

        foreach (User::query()->whereIn('role', ['admin', 'superadmin'])->get() as $admin) {
            FilamentNotification::make()
                ->title($title)
                ->body($body)
                ->danger()
                ->sendToDatabase($admin);
        }
    }

    protected static function ownOrders(string $siteId): Builder
    {
        return Order::query()
            ->where('site_id', $siteId)
            ->whereNotIn('invoice_id', ['PROFORMA', 'RETURN'])
            ->where(fn (Builder $query) => $query->whereNull('order_origin')->orWhereIn('order_origin', ['own', 'pos']));
    }

    protected static function paidOrders(string $siteId, Carbon $from, Carbon $to): Builder
    {
        return self::ownOrders($siteId)->isPaid()->whereBetween('created_at', [$from, $to]);
    }
}
