<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use UnitEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsTable;

class ActionsStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Actie statistieken';
    protected static string | UnitEnum | null $navigationGroup = 'Statistics';
    protected static ?string $title = 'Actie statistieken';
    protected static ?int $navigationSort = 100000;

    protected string $view = 'dashed-ecommerce-core::statistics.pages.action-statistics';

    public ?array $data = [];

    public array $graphData = [];

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subMonth(),
            'endDate' => now(),
        ]);

        $this->calculateStatistics();
    }

    public function updated(string $propertyName): void
    {
        if (str_starts_with($propertyName, 'data.')) {
            $this->calculateStatistics();
        }
    }

    protected function calculateStatistics(): void
    {
        $state = $this->form->getState();

        $beginDate = ! empty($state['startDate'])
            ? Carbon::parse($state['startDate'])->startOfDay()
            : now()->subMonth()->startOfDay();

        $endDate = ! empty($state['endDate'])
            ? Carbon::parse($state['endDate'])->endOfDay()
            : now()->endOfDay();

        $baseQuery = DB::table('dashed__ecommerce_action_logs as eal')
            ->whereBetween('eal.created_at', [$beginDate, $endDate])
            ->whereIn('eal.action_type', ['add_to_cart', 'remove_from_cart']);

        $totals = (clone $baseQuery)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN eal.action_type = 'add_to_cart' THEN eal.quantity ELSE 0 END), 0) as total_add_to_carts,
                COALESCE(SUM(CASE WHEN eal.action_type = 'remove_from_cart' THEN eal.quantity ELSE 0 END), 0) as total_remove_from_carts
            ")
            ->first();

        $productStats = (clone $baseQuery)
            ->join('dashed__products as p', 'p.id', '=', 'eal.product_id')
            ->selectRaw("
                p.id,
                p.name,
                COALESCE(SUM(CASE WHEN eal.action_type = 'add_to_cart' THEN eal.quantity ELSE 0 END), 0) as add_to_cart_count,
                COALESCE(SUM(CASE WHEN eal.action_type = 'remove_from_cart' THEN eal.quantity ELSE 0 END), 0) as remove_from_cart_count
            ")
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('add_to_cart_count')
            ->get();

        $mostAddedProduct = $productStats
            ->sortByDesc('add_to_cart_count')
            ->first();

        $mostRemovedProduct = $productStats
            ->sortByDesc('remove_from_cart_count')
            ->first();

        $graphRows = (clone $baseQuery)
            ->selectRaw("
                DATE(eal.created_at) as date,
                COALESCE(SUM(CASE WHEN eal.action_type = 'add_to_cart' THEN eal.quantity ELSE 0 END), 0) as add_to_cart_total,
                COALESCE(SUM(CASE WHEN eal.action_type = 'remove_from_cart' THEN eal.quantity ELSE 0 END), 0) as remove_from_cart_total
            ")
            ->groupByRaw('DATE(eal.created_at)')
            ->orderByRaw('DATE(eal.created_at)')
            ->get()
            ->keyBy('date');

        $graphLabels = [];
        $graphAddData = [];
        $graphRemoveData = [];

        $cursor = $beginDate->copy()->startOfDay();
        $lastDay = $endDate->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $dateKey = $cursor->format('Y-m-d');

            $graphLabels[] = $cursor->format('d-m-Y');
            $graphAddData[] = (int) ($graphRows[$dateKey]->add_to_cart_total ?? 0);
            $graphRemoveData[] = (int) ($graphRows[$dateKey]->remove_from_cart_total ?? 0);

            $cursor->addDay();
        }

        $days = max(1, $beginDate->diffInDays($endDate) + 1);

        $totalAddToCarts = (int) ($totals->total_add_to_carts ?? 0);
        $totalRemoveFromCarts = (int) ($totals->total_remove_from_carts ?? 0);

        $this->graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Add to carts',
                        'data' => $graphAddData,
                        'backgroundColor' => 'orange',
                        'borderColor' => 'orange',
                        'fill' => 'start',
                    ],
                    [
                        'label' => 'Remove from carts',
                        'data' => $graphRemoveData,
                        'backgroundColor' => 'blue',
                        'borderColor' => 'blue',
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graphLabels,
            ],
            'filters' => [
                'beginDate' => $beginDate->toDateTimeString(),
                'endDate' => $endDate->toDateTimeString(),
            ],
            'data' => [
                'totalAddToCarts' => $totalAddToCarts,
                'totalRemoveFromCarts' => $totalRemoveFromCarts,
                'averagePerDayAddToCarts' => number_format($totalAddToCarts / $days, 2, '.', ','),
                'averagePerDayRemoveFromCarts' => number_format($totalRemoveFromCarts / $days, 2, '.', ','),
                'mostAddedProduct' => $mostAddedProduct
                    ? $mostAddedProduct->name . ' (' . $mostAddedProduct->add_to_cart_count . ')'
                    : 'geen product (0)',
                'mostRemovedProduct' => $mostRemovedProduct
                    ? $mostRemovedProduct->name . ' (' . $mostRemovedProduct->remove_from_cart_count . ')'
                    : 'geen product (0)',
            ],
            'products' => $productStats,
        ];

        $this->dispatch('updateGraphData', $this->graphData);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start datum')
                            ->default(now()->subMonth())
                            ->reactive(),

                        DatePicker::make('endDate')
                            ->label('Eind datum')
                            ->nullable()
                            ->after('startDate')
                            ->default(now())
                            ->reactive(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFooterWidgets(): array
    {
        return [
            ActionStatisticsChart::class,
            ActionStatisticsCards::class,
            ActionStatisticsTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
