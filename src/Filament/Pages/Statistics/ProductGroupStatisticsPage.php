<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use UnitEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Dashed\DashedCore\Classes\Locales;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ProductGroupTable;

class ProductGroupStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Product group statistieken';
    protected static string | UnitEnum | null $navigationGroup = 'Statistics';
    protected static ?string $title = 'Product group statistieken';
    protected static ?int $navigationSort = 100000;

    protected string $view = 'dashed-ecommerce-core::statistics.pages.product-statistics';

    /**
     * Form state (filters).
     */
    public ?array $data = [];

    /**
     * Graph + stats data voor widgets / JS.
     */
    public $graphData;

    public function mount(): void
    {
        // Defaults uit schema laten vullen
        $this->form->fill();

        $this->getStatisticsProperty();
    }

    public function updated(string $propertyName): void
    {
        if (str_starts_with($propertyName, 'data.')) {
            $this->getStatisticsProperty();
        }
    }

    public function submit(): void
    {
        $this->getStatisticsProperty();
    }

    public function getStatisticsProperty(): void
    {
        $state = $this->form->getState();

        $beginDate = ! empty($state['startDate'])
            ? Carbon::parse($state['startDate'])
            : now()->subMonth();

        $endDate = ! empty($state['endDate'])
            ? Carbon::parse($state['endDate'])
            : now()->addDay();

        $search = $state['search'] ?? null;
        $locale = $state['locale'] ?? null;

        $productGroupsQuery = ProductGroup::query();

        if (! empty($search)) {
            $productGroupsQuery->whereRaw('LOWER(name) like ?', '%' . strtolower($search) . '%');
        }

        $productGroups = $productGroupsQuery
            ->latest()
            ->get();

        $orderIdsQuery = Order::isPaid()
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate);

        if (! empty($locale)) {
            $orderIdsQuery->where('locale', $locale);
        }

        $orderIds = $orderIdsQuery->pluck('id');

        $orderProducts = OrderProduct::whereIn('order_id', $orderIds)->get();

        $totalQuantitySold = 0;
        $totalAmountSold = 0;
        $averageCostPerProduct = 0;

        foreach ($productGroups as $productGroup) {
            $productIds = $productGroup->products->pluck('id');

            $productGroup->quantitySold = $orderProducts
                ->whereIn('product_id', $productIds)
                ->sum('quantity');

            $productGroup->amountSold = $orderProducts
                ->whereIn('product_id', $productIds)
                ->sum('price');

            $totalQuantitySold += $productGroup->quantitySold;
            $totalAmountSold += $productGroup->amountSold;
        }

        if ($totalQuantitySold) {
            $averageCostPerProduct = $totalAmountSold / $totalQuantitySold;
        }

        $statistics = [
            'totalQuantitySold' => $totalQuantitySold,
            'totalAmountSold' => CurrencyHelper::formatPrice($totalAmountSold),
            'averageCostPerProduct' => CurrencyHelper::formatPrice($averageCostPerProduct),
        ];

        $graph = [
            'data' => [],
            'labels' => [],
        ];

        $graphBeginDate = $beginDate->copy();

        while ($graphBeginDate < $endDate) {
            $graph['data'][] = OrderProduct::whereIn('id', $orderProducts->pluck('id'))
                ->whereIn(
                    'product_id',
                    Product::whereIn('product_group_id', $productGroups->pluck('id'))->pluck('id')
                )
                ->where('created_at', '>=', $graphBeginDate->copy()->startOfDay())
                ->where('created_at', '<=', $graphBeginDate->copy()->endOfDay())
                ->sum('quantity');

            $graph['labels'][] = $graphBeginDate->format('d-m-Y');

            $graphBeginDate->addDay();
        }

        $graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Stats',
                        'data' => $graph['data'] ?? [],
                        'backgroundColor' => 'orange',
                        'borderColor' => 'orange',
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graph['labels'] ?? [],
            ],
            'filters' => [
                'search' => $search,
                'beginDate' => $beginDate,
                'endDate' => $endDate,
                'locale' => $locale,
            ],
            'data' => $statistics,
            'products' => $productGroups,
        ];

        $this->graphData = $graphData;

        $this->dispatch('updateGraphData', $graphData);
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
                    ->columnSpanFull()
                    ->columns([
                        'default' => 1,
                        'lg' => 4,
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFooterWidgets(): array
    {
        return [
            ProductGroupChart::class,
            ProductGroupCards::class,
            ProductGroupTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
