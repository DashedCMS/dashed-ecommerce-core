<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Pages;

use BackedEnum;
use UnitEnum;
use Filament\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Support\InsightsService;

/**
 * Inzichten in het CMS: cashflow-puls + voorspellend inkoopadvies. Gebruikt
 * dezelfde InsightsService als de mobiele app, dus identieke cijfers.
 */
class InsightsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string|UnitEnum|null $navigationGroup = 'Statistics';

    protected static ?string $navigationLabel = 'Inzichten';

    protected static ?string $title = 'Inzichten — cashflow & inkoopadvies';

    protected static ?int $navigationSort = 0;

    protected string $view = 'dashed-ecommerce-core::filament.pages.insights';

    /** @var array<string, float|int> */
    public array $cashflow = [];

    /** @var array<int, array<string, mixed>> */
    public array $reorder = [];

    /** @var array<string, int> */
    public array $meta = [];

    public function mount(): void
    {
        $service = app(InsightsService::class);
        $site = (string) Sites::getActive();

        $this->cashflow = $service->cashflow($site);
        $this->reorder = $service->reorderAdvice($site);
        $this->meta = [
            'velocity_days' => $service->velocityDays,
            'horizon_days' => $service->horizonDays,
            'cover_days' => $service->coverDays,
        ];
    }

    public function euro(float|int $n): string
    {
        return '€ ' . number_format((float) $n, 2, ',', '.');
    }
}
