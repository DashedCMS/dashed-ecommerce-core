<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisPeriod;
use Dashed\DashedEcommerceCore\Support\Analysis\AnalysisContext;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisReport;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisRunner;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisNarrator;
use Dashed\DashedEcommerceCore\Support\Analysis\SalesAnalysisRegistry;

class SalesAnalysisPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static \BackedEnum | null | string $navigationIcon = 'heroicon-o-light-bulb';
    protected static \UnitEnum | string | null $navigationGroup = 'Statistieken';
    protected static ?string $navigationLabel = 'Verkoopanalyse';
    protected static ?string $title = 'Verkoopanalyse';
    protected static ?int $navigationSort = 1;

    protected string $view = 'dashed-ecommerce-core::statistics.pages.sales-analysis';

    public ?array $data = [];

    /** @var array<string, array<string, array<string, mixed>>> groep => sleutel => ['label' =>, 'facts' =>] */
    public array $sections = [];

    /** @var array<int, array<string, mixed>> */
    public array $signals = [];

    public ?string $narrative = null;

    /** @var array<string, string> */
    public array $failed = [];

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_statistics');
    }

    public function mount(): void
    {
        $this->form->fill();
        $this->calculate();
    }

    public function submit(): void
    {
        $this->calculate();
    }

    public function refreshAnalysis(): void
    {
        $this->calculate(fresh: true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label(__('Start datum'))
                            ->default(now()->startOfMonth())
                            ->required(),
                        DatePicker::make('endDate')
                            ->label(__('Eind datum'))
                            ->default(now())
                            ->afterOrEqual('startDate')
                            ->required(),
                    ])
                    ->columns(['default' => 1, 'lg' => 2]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label(__('Opnieuw berekenen'))
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshAnalysis()),
        ];
    }

    protected function calculate(bool $fresh = false): void
    {
        $state = $this->form->getState();

        $period = AnalysisPeriod::make(
            $state['startDate'] ?? now()->startOfMonth(),
            $state['endDate'] ?? now(),
        );

        $siteId = (string) Sites::getActive();
        $context = AnalysisContext::for($period, $siteId);

        // Een uur cache met een knop ernaast. De reden is niet de rekentijd
        // maar het geld: dezelfde periode twee keer openen hoort niet twee
        // keer een AI-aanroep te kosten.
        $cacheKey = 'sales-analysis:' . $siteId . ':' . $period->cacheKey();

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember($cacheKey, now()->addHour(), function () use ($context) {
            $report = (new SalesAnalysisRunner())->run($context);

            return [
                'sections' => $this->sectionsFrom($report),
                'signals' => array_map(fn ($signal) => [
                    'severity' => $signal->severity,
                    'title' => $signal->title,
                    'explanation' => $signal->explanation,
                    'numbers' => $signal->numbers,
                    'url' => $signal->url,
                ], $report->signals()),
                'narrative' => SalesAnalysisNarrator::narrate($report, $context),
                'failed' => $report->failed,
            ];
        });

        $this->sections = $payload['sections'];
        $this->signals = $payload['signals'];
        $this->narrative = $payload['narrative'];
        $this->failed = $payload['failed'];
    }

    /**
     * @return array<string, array<string, array<string, mixed>>>
     */
    protected function sectionsFrom(SalesAnalysisReport $report): array
    {
        $map = SalesAnalysisRegistry::map();
        $sections = [];

        foreach ($report->results as $key => $result) {
            $class = $map[$key] ?? null;

            if (! $class) {
                continue;
            }

            $sections[$class::group()][$key] = [
                'label' => $class::label(),
                'facts' => $result->facts,
            ];
        }

        return $sections;
    }
}
