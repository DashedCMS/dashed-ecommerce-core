<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\ActionStatisticsTable;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueChart;

class ActionsStatisticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Actie statistieken';
    protected static ?string $navigationGroup = 'Statistics';
    protected static ?string $title = 'Actie statistieken';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::statistics.pages.action-statistics';

    public $startDate;
    public $endDate;

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subMonth(),
            'endDate' => now(),
        ]);

        $this->getStatisticsProperty();
    }

    public function updated()
    {
        $this->getStatisticsProperty();
    }

    public function getStatisticsProperty()
    {
        $beginDate = $this->startDate ? Carbon::parse($this->startDate) : now()->subMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->addDay();

        $addToCartActions = EcommerceActionLog::where('action_type', 'add_to_cart')
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate)
            ->get();
        $removeFromCartActions = EcommerceActionLog::where('action_type', 'remove_from_cart')
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate)
            ->get();

        $products = Product::query()
            ->whereIn('id', array_merge(
                $addToCartActions->pluck('product_id')->toArray(),
                $removeFromCartActions->pluck('product_id')->toArray()
            ))
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate)
            ->get();

        foreach ($products as &$product) {
            $product->add_to_cart_count = $addToCartActions->where('product_id', $product->id)->sum('quantity');
            $product->remove_from_cart_count = $removeFromCartActions->where('product_id', $product->id)->sum('quantity');
        }

        $statistics = [
            'totalAddToCarts' => $addToCartActions->sum('quantity'),
            'totalRemoveFromCarts' => $removeFromCartActions->sum('quantity'),
            'averagePerDayAddToCarts' => number_format($addToCartActions->sum('quantity') / ($beginDate->diffInDays($endDate) ?: 1), 2, '.', ','),
            'averagePerDayRemoveFromCarts' => number_format($removeFromCartActions->sum('quantity') / ($beginDate->diffInDays($endDate) ?: 1), 2, '.', ','),
            'mostAddedProduct' => ($products->sortByDesc('add_to_cart_count')->first()->name ?? 'geen product') . ' (' . ($products->sortByDesc('add_to_cart_count')->first()->add_to_cart_count ?? '0') . ')',
            'mostRemovedProduct' => ($products->sortByDesc('remove_from_cart_count')->first()->name ?? 'geen product') . ' (' . ($products->sortByDesc('remove_from_cart_count')->first()->remove_from_cart_count ?? '0') . ')',
        ];

        $graph = [];

        $graphBeginDate = $beginDate->copy();
        while ($graphBeginDate < $endDate) {
            $graph['data'][] = $addToCartActions->where('created_at', '>=', $graphBeginDate)
                ->where('created_at', '<', $graphBeginDate->copy()->addDay())->sum('quantity');
            $graph['data2'][] = $removeFromCartActions->where('created_at', '>=', $graphBeginDate)
                ->where('created_at', '<', $graphBeginDate->copy()->addDay())->sum('quantity');
            $graph['labels'][] = $graphBeginDate->format('d-m-Y');
            $graphBeginDate->addDay();
        }

        $graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Add to carts',
                        'data' => $graph['data'] ?? [],
                        'backgroundColor' => 'orange',
                        'borderColor' => "orange",
                        'fill' => 'start',
                    ],
                    [
                        'label' => 'Remove from carts',
                        'data' => $graph['data2'] ?? [],
                        'backgroundColor' => 'blue',
                        'borderColor' => "blue",
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graph['labels'] ?? [],
            ],
            'filters' => [
                'beginDate' => $beginDate,
                'endDate' => $endDate,
            ],
            'data' => $statistics,
        ];

        $graphData['products'] = $products;

        $this->graphData = $graphData;
        $this->dispatch('updateGraphData', $graphData);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Start datum')
                        ->reactive(),
                    DatePicker::make('endDate')
                        ->label('Eind datum')
                        ->nullable()
                        ->after('startDate')
                        ->reactive(),
                ])
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                ]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            ActionStatisticsChart::make(),
            ActionStatisticsCards::make(),
            ActionStatisticsTable::make(),
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
