<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Models\AutomationRuleRun;

/**
 * Kandidaat-selectie voor relatieve tijd-automatiseringsregels ("N
 * [uren|dagen] na [anker]"): welke orders komen nú in aanmerking om te
 * vuren? Site-bewust (alleen orders van `$rule->site_id`) en
 * horizon-begrensd (self::HORIZON_DAYS) zodat een lang stilgelegen regel
 * niet in één klap duizenden oude orders oppakt — die overslag wordt
 * gelogd, nooit stil weggelaten.
 *
 * "Al gedraaid" is een geslaagde AutomationRuleRun voor (regel, order): één
 * bron van waarheid, dezelfde die de loop-guard in AutomationEngine
 * gebruikt. Een mislukte of lopende run blokkeert een order dus niet —
 * die mag opnieuw geprobeerd worden.
 *
 * Pure/stateloze scan: alleen lezen. Het vuren zelf (en het wegschrijven
 * van de run) gebeurt elders (RunAutomationRuleJob / AutomationEngine).
 */
class TimeRuleScanner
{
    /**
     * Hoe ver terug ooit nog naar kandidaten wordt gezocht. Voorkomt dat een
     * regel die lang stil heeft gelegen in één klap alle historische orders
     * als kandidaat oppakt.
     */
    public const HORIZON_DAYS = 90;

    /**
     * Orders van de site van $rule waarvan de ankertijd (uit
     * $rule->schedule) in [$now - HORIZON_DAYS, $now - delay] valt, en die
     * nog geen geslaagde run voor deze regel hebben. Een ongeldige schedule
     * levert een lege collectie (plus een logregel) op — nooit een
     * exception, zodat een scan over meerdere regels niet strandt op één
     * kapotte regel.
     */
    public static function relativeCandidates(AutomationRule $rule, Carbon $now): Collection
    {
        $schedule = $rule->schedule ?? [];
        $anchor = (string) ($schedule['anchor'] ?? '');
        $amount = (int) ($schedule['amount'] ?? 0);
        $unit = (string) ($schedule['unit'] ?? '');

        if (! in_array($anchor, TimeAnchors::KEYS, true) || $amount < 1 || ! in_array($unit, ['hours', 'days'], true)) {
            Log::info("[time-automation] regel {$rule->id} heeft een ongeldige relative schedule, overgeslagen.");

            return collect();
        }

        $delayMoment = $unit === 'hours' ? $now->copy()->subHours($amount) : $now->copy()->subDays($amount);
        $horizonMoment = $now->copy()->subDays(self::HORIZON_DAYS);

        $ordersTable = (new Order())->getTable();
        $runsTable = (new AutomationRuleRun())->getTable();

        $query = Order::query()->where('site_id', (string) $rule->site_id);
        TimeAnchors::applyBefore($query, $anchor, $delayMoment); // anker <= now - delay
        $overHorizon = (clone $query)->count();

        TimeAnchors::applyAfter($query, $anchor, $horizonMoment); // anker >= now - horizon
        $query->whereNotExists(function ($subQuery) use ($runsTable, $ordersTable, $rule) {
            $subQuery->from($runsTable)
                ->whereColumn("$runsTable.subject_id", "$ordersTable.id")
                ->where("$runsTable.subject_type", Order::class)
                ->where("$runsTable.rule_id", $rule->id)
                ->where("$runsTable.status", AutomationRuleRun::STATUS_SUCCESS);
        });

        $candidates = $query->get();

        $skipped = $overHorizon - $candidates->count();
        if ($skipped > 0) {
            Log::info("[time-automation] regel {$rule->id}: {$skipped} orders buiten de ".self::HORIZON_DAYS.'-dagen horizon of al gedraaid.');
        }

        return $candidates;
    }

    /**
     * Is de cadans van een terugkerende regel (schedule.mode === 'recurring')
     * nú bereikt? `daily`: zodra $now het dagelijkse tijdstip (schedule.at)
     * heeft bereikt of gepasseerd is. `weekly`: bovendien alleen op de
     * geconfigureerde weekdag (schedule.weekday, ISO 1=maandag..7=zondag).
     * Een niet-recurring of anderszins ongeldige schedule levert false op.
     */
    public static function recurringDue(AutomationRule $rule, Carbon $now): bool
    {
        $schedule = $rule->schedule ?? [];

        if (($schedule['mode'] ?? null) !== 'recurring') {
            return false;
        }

        [$hour, $minute] = array_pad(explode(':', (string) ($schedule['at'] ?? '')), 2, '0');
        $at = $now->copy()->setTime((int) $hour, (int) $minute);

        if ($now->lt($at)) {
            return false;
        }

        if (($schedule['frequency'] ?? 'daily') === 'weekly') {
            return $now->dayOfWeekIso === (int) ($schedule['weekday'] ?? 0);
        }

        return true;
    }

    /**
     * Orders van de site van $rule die in aanmerking komen voor de huidige
     * cadans van een terugkerende regel: als recurringDue() false is (de
     * cadans is nog niet bereikt, of het is de verkeerde dag), levert dit
     * altijd een lege collectie op. Anders: orders binnen de horizon
     * (self::HORIZON_DAYS) zonder een geslaagde run sinds het begin van de
     * *huidige periode* (dag voor daily, week voor weekly) — een geslaagde
     * run uit een eerdere periode blokkeert dus niet, zodat de regel de
     * volgende periode weer vuurt voor dezelfde order.
     */
    public static function recurringCandidates(AutomationRule $rule, Carbon $now): Collection
    {
        if (! self::recurringDue($rule, $now)) {
            return collect();
        }

        $schedule = $rule->schedule ?? [];
        $periodStart = ($schedule['frequency'] ?? 'daily') === 'weekly'
            ? $now->copy()->startOfWeek()
            : $now->copy()->startOfDay();

        $ordersTable = (new Order())->getTable();
        $runsTable = (new AutomationRuleRun())->getTable();

        return Order::query()
            ->where('site_id', (string) $rule->site_id)
            ->where("$ordersTable.created_at", '>=', $now->copy()->subDays(self::HORIZON_DAYS))
            ->whereNotExists(function ($subQuery) use ($runsTable, $ordersTable, $rule, $periodStart) {
                $subQuery->from($runsTable)
                    ->whereColumn("$runsTable.subject_id", "$ordersTable.id")
                    ->where("$runsTable.subject_type", Order::class)
                    ->where("$runsTable.rule_id", $rule->id)
                    ->where("$runsTable.status", AutomationRuleRun::STATUS_SUCCESS)
                    ->where("$runsTable.created_at", '>=', $periodStart);
            })
            ->get();
    }
}
