<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports\Concerns;

trait HasDateRangePresets
{
    public function fillDateRange(string $preset): void
    {
        [$start, $end] = match ($preset) {
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'last_quarter' => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            default => [now(), now()],
        };

        $startField = $this->startDateField ?? 'start_date';
        $endField = $this->endDateField ?? 'end_date';

        if (! is_array($this->data)) {
            $this->data = [];
        }

        $this->data[$startField] = $start->format('Y-m-d');
        $this->data[$endField] = $end->format('Y-m-d');
    }
}
