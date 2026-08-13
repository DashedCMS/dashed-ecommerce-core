<?php

namespace Dashed\DashedEcommerceCore\Support\Analysis\Analyses;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisResult;
use Dashed\DashedEcommerceCore\Support\Analysis\OrderLineQuery;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\Contracts\SalesAnalysis;

/**
 * Omzet per dag of per week. Lege dagen krijgen een nul in plaats van te
 * ontbreken, anders liegt de grafiek over de vorm van de periode.
 */
class TimelineAnalysis implements SalesAnalysis
{
    /** Vanaf deze lengte per week in plaats van per dag. */
    protected const WEEKLY_FROM_DAYS = 70;

    public static function key(): string
    {
        return 'verloop';
    }

    public static function label(): string
    {
        return __('Verloop');
    }

    public static function group(): string
    {
        return 'verkoop';
    }

    public static function isAvailable(AnalysisContext $context): bool
    {
        return true;
    }

    public function run(AnalysisContext $context): AnalysisResult
    {
        $weekly = $context->period->days() >= self::WEEKLY_FROM_DAYS;

        // Per dag optellen in de database in plaats van elke order als model
        // op te halen: bij tienduizenden orders in een periode is dat het
        // verschil tussen een query en een geheugengrens. DATE() werkt zowel
        // op MySQL (productie) als op SQLite (tests).
        //
        // Hier bewust niet afronden. Dat gebeurde eerst per dag én daarna
        // nog eens over de weeksom, waardoor de grafiek een paar cent van
        // het kerncijfer af kon liggen; nu wordt er alleen aan het eind
        // afgerond.
        $revenuePerDay = OrderLineQuery::orders($context->period, $context->siteId)
            ->selectRaw('DATE(created_at) as day, COALESCE(SUM(total), 0) as revenue')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('revenue', 'day');

        $labels = [];
        $revenue = [];

        $cursor = $context->period->start;
        $last = $context->period->end->startOfDay();

        while ($cursor->lessThanOrEqualTo($last)) {
            $step = $weekly ? $cursor->addDays(6) : $cursor;
            $stop = $step->greaterThan($last) ? $last : $step;

            $sum = 0.0;
            $day = $cursor;
            while ($day->lessThanOrEqualTo($stop)) {
                $sum += (float) ($revenuePerDay[$day->toDateString()] ?? 0.0);
                $day = $day->addDay();
            }

            $labels[] = $weekly
                ? $cursor->format('d-m') . ' - ' . $stop->format('d-m')
                : $cursor->format('d-m-Y');
            $revenue[] = round($sum, 2);

            $cursor = $stop->addDay();
        }

        return new AnalysisResult(facts: [
            'interval' => $weekly ? 'week' : 'day',
            'labels' => $labels,
            'revenue' => $revenue,
        ]);
    }
}
