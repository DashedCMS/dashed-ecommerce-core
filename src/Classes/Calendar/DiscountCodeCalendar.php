<?php

namespace Dashed\DashedEcommerceCore\Classes\Calendar;

use Illuminate\Support\Carbon;
use Dashed\DashedCore\Classes\Trash\Trash;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedCore\Classes\Calendar\CalendarEvent;
use Dashed\DashedCore\Classes\Calendar\CampaignCalendar;

/**
 * Kortingscodes op de campagnekalender. Alleen codes met een start- of
 * einddatum: een code die altijd geldt is geen campagne en zou elke dag
 * vullen. Twee automatische (globale) kortingen tegelijk is een conflict,
 * want die stapelen op elke bestelling.
 */
class DiscountCodeCalendar
{
    public static function register(): void
    {
        if (! class_exists(CampaignCalendar::class)) {
            return;
        }

        CampaignCalendar::source('discount-codes', __('Kortingscodes'), '#16a34a', function (Carbon $from, Carbon $to, ?string $siteId) {
            return DiscountCode::query()
                ->where(fn ($q) => $q->whereNotNull('start_date')->orWhereNotNull('end_date'))
                ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $to))
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
                ->when($siteId, fn ($q) => $q->where(fn ($q) => $q->whereNull('site_ids')->orWhere('site_ids', 'LIKE', '%"'.$siteId.'"%')))
                ->limit(200)
                ->get()
                ->map(fn (DiscountCode $code) => new CalendarEvent(
                    source: 'discount-codes',
                    title: ($code->is_global_discount ? __('Automatische korting') : __('Code :code', ['code' => $code->code])).($code->name ? ' ('.$code->name.')' : ''),
                    start: $code->start_date ? Carbon::parse($code->start_date) : $from->copy(),
                    end: $code->end_date ? Carbon::parse($code->end_date) : null,
                    url: Trash::editUrl($code),
                    overlapKeys: $code->is_global_discount ? ['global-discount'] : [],
                ))
                ->all();
        });
    }
}
