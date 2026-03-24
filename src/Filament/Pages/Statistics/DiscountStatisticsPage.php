<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use UnitEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\DiscountTable;

class DiscountStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Korting statistieken';
    protected static string | UnitEnum | null $navigationGroup = 'Statistics';
    protected static ?string $title = 'Korting statistieken';
    protected static ?int $navigationSort = 100000;

    protected string $view = 'dashed-ecommerce-core::statistics.pages.discount-statistics';

    public ?array $data = [];

    public array $graphData = [];

    public function mount(): void
    {
        $this->form->fill([
            'discountCode' => 'all',
            'status' => 'payment_obligation',
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

        $discountCodeValue = $state['discountCode'] ?? 'all';
        $status = $state['status'] ?? 'payment_obligation';

        $discountCodeId = null;

        if ($discountCodeValue !== 'all') {
            $discountCodeId = DiscountCode::query()
                ->where('code', $discountCodeValue)
                ->value('id');

            if (! $discountCodeId) {
                $this->graphData = [
                    'graph' => [
                        'datasets' => [
                            [
                                'label' => 'Korting',
                                'data' => [],
                                'backgroundColor' => 'orange',
                                'borderColor' => 'orange',
                                'fill' => 'start',
                            ],
                        ],
                        'labels' => [],
                    ],
                    'filters' => [
                        'beginDate' => $beginDate->toDateTimeString(),
                        'endDate' => $endDate->toDateTimeString(),
                        'discountCode' => $discountCodeValue,
                        'status' => $status,
                    ],
                    'data' => [
                        'ordersAmount' => 0,
                        'orderAmount' => CurrencyHelper::formatPrice(0),
                        'discountAmount' => CurrencyHelper::formatPrice(0),
                        'averageDiscountAmount' => CurrencyHelper::formatPrice(0),
                        'averageOrderAmount' => CurrencyHelper::formatPrice(0),
                        'productsSold' => 0,
                    ],
                    'orders' => collect(),
                ];

                $this->dispatch('updateGraphData', $this->graphData);

                return;
            }
        }

        $ordersQuery = Order::query()
            ->whereBetween('created_at', [$beginDate, $endDate]);

        if ($discountCodeId) {
            $ordersQuery->where('discount_code_id', $discountCodeId);
        }

        if ($status === 'payment_obligation') {
            $ordersQuery->isPaid();
        } elseif ($status !== 'all') {
            $ordersQuery->where('status', $status);
        }

        $filteredOrdersQuery = clone $ordersQuery;

        $orderTotals = (clone $filteredOrdersQuery)
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(total), 0) as total_amount,
                COALESCE(SUM(discount), 0) as total_discount
            ')
            ->first();

        $filteredOrderIds = (clone $filteredOrdersQuery)->select('id');

        $productsSold = DB::table('dashed__order_products')
            ->whereIn('order_id', $filteredOrderIds)
            ->whereNotIn('sku', ['product_costs', 'shipping_costs', 'payment_costs'])
            ->sum('quantity');

        $graphRows = (clone $filteredOrdersQuery)
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(discount), 0) as total_discount')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get()
            ->keyBy('date');

        $graphLabels = [];
        $graphValues = [];

        $cursor = $beginDate->copy()->startOfDay();
        $lastDay = $endDate->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $dateKey = $cursor->format('Y-m-d');

            $graphLabels[] = $cursor->format('d-m-Y');
            $graphValues[] = (float) ($graphRows[$dateKey]->total_discount ?? 0);

            $cursor->addDay();
        }

        $totalOrderCount = (int) ($orderTotals->total_orders ?? 0);
        $totalAmount = (float) ($orderTotals->total_amount ?? 0);
        $discountAmount = (float) ($orderTotals->total_discount ?? 0);

        $averageOrderAmount = $totalOrderCount > 0
            ? $totalAmount / $totalOrderCount
            : 0;

        $averageDiscountAmount = $totalOrderCount > 0
            ? $discountAmount / $totalOrderCount
            : 0;

        $orders = (clone $filteredOrdersQuery)
            ->latest()
            ->get();

        $this->graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Korting',
                        'data' => $graphValues,
                        'backgroundColor' => 'orange',
                        'borderColor' => 'orange',
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graphLabels,
            ],
            'filters' => [
                'beginDate' => $beginDate->toDateTimeString(),
                'endDate' => $endDate->toDateTimeString(),
                'discountCode' => $discountCodeValue,
                'status' => $status,
            ],
            'data' => [
                'ordersAmount' => $totalOrderCount,
                'orderAmount' => CurrencyHelper::formatPrice($totalAmount),
                'discountAmount' => CurrencyHelper::formatPrice($discountAmount),
                'averageDiscountAmount' => CurrencyHelper::formatPrice($averageDiscountAmount),
                'averageOrderAmount' => CurrencyHelper::formatPrice($averageOrderAmount),
                'productsSold' => (int) $productsSold,
            ],
            'orders' => $orders,
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

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'all' => 'Alles',
                                'payment_obligation' => 'Betalingsverplichting',
                                'paid' => 'Betaald',
                                'waiting_for_confirmation' => 'Wachten op bevestiging',
                                'pending' => 'Lopende aankoop',
                                'cancelled' => 'Geannuleerd',
                                'return' => 'Retour',
                            ])
                            ->default('payment_obligation')
                            ->reactive(),

                        Select::make('discountCode')
                            ->label('Kortingscode')
                            ->options(array_merge([
                                'all' => 'Alles',
                            ], DiscountCode::query()->pluck('name', 'code')->toArray()))
                            ->default('all')
                            ->reactive(),
                    ])
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
            DiscountChart::class,
            DiscountCards::class,
            DiscountTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
