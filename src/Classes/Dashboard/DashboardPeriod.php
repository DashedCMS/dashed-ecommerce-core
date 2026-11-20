<?php

namespace Dashed\DashedEcommerceCore\Classes\Dashboard;

use Carbon\Carbon;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

/**
 * De periode uit de dashboardfilters (Dashboard::getStartData(), bijgewerkt
 * via setPageFiltersData) plus de gelijke periode direct daarvoor, voor de
 * delta's op de KPI's. Zonder filters: de laatste 30 dagen.
 */
class DashboardPeriod
{
    public function __construct(
        public readonly Carbon $start,
        public readonly Carbon $end,
        public readonly Carbon $previousStart,
        public readonly Carbon $previousEnd,
        public readonly string $steps,
        public readonly ?string $label,
    ) {
    }

    public static function fromFilters(?array $filters): self
    {
        $filters ??= [];
        $steps = (string) ($filters['steps'] ?? 'per_day');
        if (! in_array($steps, ['per_hour', 'per_day', 'per_week', 'per_month', 'per_quarter', 'per_year'], true)) {
            $steps = 'per_day';
        }

        $formats = Dashboard::getFormatsByStep($steps);

        $start = ! empty($filters['startDate'])
            ? Carbon::parse($filters['startDate'])->{$formats['startFormat']}()
            : now()->subDays(30)->startOfDay();
        $end = ! empty($filters['endDate'])
            ? Carbon::parse($filters['endDate'])->{$formats['endFormat']}()
            : now()->endOfDay();

        if ($end < $start) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $length = (int) $start->diffInSeconds($end, true);
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subSeconds($length);

        $label = Dashboard::getPeriodOptions()[$filters['period'] ?? ''] ?? null;

        return new self($start, $end, $previousStart, $previousEnd, $steps, $label);
    }

    /**
     * Procentuele verandering, of null als er in de vorige periode niets was
     * om tegen af te zetten (een delta van "oneindig" zegt niemand iets).
     */
    public static function delta(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.00001) {
            return null;
        }

        return ($current - $previous) / abs($previous) * 100;
    }

    public static function formatDelta(?float $delta): ?string
    {
        if ($delta === null) {
            return null;
        }

        return ($delta >= 0 ? '+' : '-').number_format(abs($delta), 1, ',', '.').'%';
    }
}
