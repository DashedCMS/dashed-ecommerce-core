<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductTable;

class ProductStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static \BackedEnum | null | string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Product statistieken';
    protected static \UnitEnum | string | null $navigationGroup = 'Statistics';
    protected static ?string $title = 'Product statistieken';
    protected static ?int $navigationSort = 100000;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_statistics');
    }


    protected string $view = 'dashed-ecommerce-core::statistics.pages.product-statistics';

    public ?array $data = [];

    public array $graphData = [];

    public function mount(): void
    {
        $this->form->fill();
        $this->calculateStatistics();
    }

    public function updated(string $propertyName): void
    {
        if (str_starts_with($propertyName, 'data.')) {
            $this->calculateStatistics();
        }
    }

    public function submit(): void
    {
        $this->calculateStatistics();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
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

                        Select::make('locale')
                            ->label('Locale')
                            ->nullable()
                            ->options(function () {
                                $locales = [];

                                foreach (Locales::getActivatedLocalesFromSites() as $locale) {
                                    $locales[$locale] = $locale;
                                }

                                return $locales;
                            })
                            ->reactive(),

                        TextInput::make('search')
                            ->label('Zoekterm')
                            ->reactive(),
                    ])
                    ->columns([
                        'default' => 1,
                        'lg' => 4,
                    ]),
            ])
            ->statePath('data');
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

        $search = $state['search'] ?? null;
        $locale = $state['locale'] ?? null;

        $baseOrderProductsQuery = DB::table('dashed__order_products as op')
            ->join('dashed__orders as o', 'o.id', '=', 'op.order_id')
            ->join('dashed__products as p', 'p.id', '=', 'op.product_id')
            ->whereBetween('o.created_at', [$beginDate, $endDate])
            ->whereNotNull('op.product_id');

        // Gebruik hier jouw exacte "paid" voorwaarden als scope niet via query builder kan
        // Voorbeeld: $baseOrderProductsQuery->where('o.status', 'paid');

        $paidOrderIds = Order::isPaid()
            ->whereBetween('created_at', [$beginDate, $endDate])
            ->when($locale, fn ($query) => $query->where('locale', $locale))
            ->select('id');

        $baseOrderProductsQuery->whereIn('o.id', $paidOrderIds);

        if (! empty($search)) {
            $baseOrderProductsQuery->where('p.name', 'like', '%' . $search . '%');
        }

        $productStats = (clone $baseOrderProductsQuery)
            ->selectRaw('
                p.id,
                p.name,
                SUM(op.quantity) as quantity_sold,
                SUM(op.price) as amount_sold
            ')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('quantity_sold')
            ->get();

        $totalQuantitySold = (int) $productStats->sum('quantity_sold');
        $totalAmountSold = (float) $productStats->sum('amount_sold');
        $averageCostPerProduct = $totalQuantitySold > 0
            ? $totalAmountSold / $totalQuantitySold
            : 0;

        $graphRows = (clone $baseOrderProductsQuery)
            ->selectRaw('DATE(o.created_at) as date, SUM(op.quantity) as total_quantity')
            ->groupByRaw('DATE(o.created_at)')
            ->orderByRaw('DATE(o.created_at)')
            ->get()
            ->keyBy('date');

        $graphLabels = [];
        $graphValues = [];

        $cursor = $beginDate->copy()->startOfDay();
        $lastDay = $endDate->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $dateKey = $cursor->format('Y-m-d');

            $graphLabels[] = $cursor->format('d-m-Y');
            $graphValues[] = (int) ($graphRows[$dateKey]->total_quantity ?? 0);

            $cursor->addDay();
        }

        $statistics = [
            'totalQuantitySold' => $totalQuantitySold,
            'totalAmountSold' => CurrencyHelper::formatPrice($totalAmountSold),
            'averageCostPerProduct' => CurrencyHelper::formatPrice($averageCostPerProduct),
        ];

        $this->graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Verkochte aantallen',
                        'data' => $graphValues,
                        'backgroundColor' => 'orange',
                        'borderColor' => 'orange',
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graphLabels,
            ],
            'filters' => [
                'search' => $search,
                'beginDate' => $beginDate->toDateTimeString(),
                'endDate' => $endDate->toDateTimeString(),
                'locale' => $locale,
            ],
            'data' => $statistics,
            'products' => $productStats,
        ];

        $this->dispatch('updateGraphData', $this->graphData);
    }

    protected function getFooterWidgets(): array
    {
        return [
             ProductChart::class,
             ProductCards::class,
             ProductTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
