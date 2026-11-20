<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Aurora\Concerns;

use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Classes\Dashboard\DashboardPeriod;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

/**
 * Dezelfde periodekeuze als de bestaande omzetwidgets: begint op de
 * opgeslagen filter van de gebruiker en volgt `setPageFiltersData`.
 */
trait UsesDashboardPeriod
{
    public ?array $filters = [];

    public function mount(): void
    {
        $this->filters = Dashboard::getStartData();
    }

    protected function getListeners()
    {
        return array_merge(parent::getListeners(), ['setPageFiltersData' => 'setPageFiltersData']);
    }

    public function setPageFiltersData($data): void
    {
        $this->filters = is_array($data) ? $data : [];
    }

    protected function period(): DashboardPeriod
    {
        return DashboardPeriod::fromFilters($this->filters);
    }

    public static function canView(): bool
    {
        return (bool) rescue(fn () => OrderResource::canViewAny(), false, false);
    }
}
