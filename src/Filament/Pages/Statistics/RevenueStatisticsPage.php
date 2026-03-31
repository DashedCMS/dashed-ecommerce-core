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
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueChart;

class RevenueStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Omzet statistieken';
    protected static string | UnitEnum | null $navigationGroup = 'Statistics';
    protected static ?string $title = 'Omzet statistieken';
    protected static ?int $navigationSort = 100000;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_statistics');
    }

    protected string $view = 'dashed-ecommerce-core::statistics.pages.revenue-statistics';

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

    public function form(Schema $schema): Schema
    {
        $paymentMethods = PaymentMethod::query()
            ->pluck('name', 'id')
            ->toArray();

        $legacyPaymentMethods = OrderPayment::query()
            ->whereNotNull('payment_method')
            ->distinct()
            ->pluck('payment_method')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($paymentMethod) => [$paymentMethod => $paymentMethod])
            ->toArray();

        $orderOrigins = Order::query()
            ->whereNotNull('order_origin')
            ->distinct()
            ->pluck('order_origin')
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($orderOrigin) => [$orderOrigin => ucfirst($orderOrigin)])
            ->toArray();

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
                            ->default('all')
                            ->reactive(),

                        Select::make('paymentMethod')
                            ->label('Betalingsmethode')
                            ->options(array_merge([
                                'all' => 'Alles',
                            ], $paymentMethods, $legacyPaymentMethods))
                            ->default('all')
                            ->reactive(),

                        Select::make('fulfillmentStatus')
                            ->label('Fulfillment status')
                            ->options([
                                'all' => 'Alles',
                                'handled' => 'Afgehandeld',
                                'unhandled' => 'Niet afgehandeld',
                            ])
                            ->default('all')
                            ->reactive(),

                        Select::make('retourStatus')
                            ->label('Retour status')
                            ->options([
                                'all' => 'Alles',
                                'handled' => 'Afgehandeld',
                                'unhandled' => 'Niet afgehandeld',
                                'received' => 'Ontvangen',
                                'shipped' => 'Onderweg',
                                'waiting_for_return' => 'Wachten op retour',
                            ])
                            ->default('all')
                            ->reactive(),

                        Select::make('orderOrigin')
                            ->label('Bestellings herkomst')
                            ->options(array_merge([
                                'all' => 'Alles',
                            ], $orderOrigins))
                            ->default('all')
                            ->reactive(),
                    ])
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'md' => 3,
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

        $status = $state['status'] ?? 'all';
        $paymentMethod = $state['paymentMethod'] ?? 'all';
        $fulfillmentStatus = $state['fulfillmentStatus'] ?? 'all';
        $retourStatus = $state['retourStatus'] ?? 'all';
        $orderOrigin = $state['orderOrigin'] ?? 'all';

        $ordersQuery = Order::query()
            ->whereBetween('created_at', [$beginDate, $endDate]);

        if ($status === 'payment_obligation') {
            $ordersQuery->isPaid();
        } elseif ($status !== 'all') {
            $ordersQuery->where('status', $status);
        }

        if ($fulfillmentStatus !== 'all') {
            $ordersQuery->where('fulfillment_status', $fulfillmentStatus);
        }

        if ($retourStatus !== 'all') {
            $ordersQuery->where('retour_status', $retourStatus);
        }

        if ($orderOrigin !== 'all') {
            $ordersQuery->where('order_origin', $orderOrigin);
        }

        if ($paymentMethod !== 'all') {
            $paymentMethodModel = is_numeric($paymentMethod)
                ? PaymentMethod::find($paymentMethod)
                : null;

            $matchingOrderIds = OrderPayment::query()
                ->when(
                    $paymentMethodModel,
                    fn ($query) => $query->where('payment_method_id', $paymentMethodModel->id),
                    fn ($query) => $query->where('payment_method', $paymentMethod)
                )
                ->select('order_id');

            $ordersQuery->whereIn('id', $matchingOrderIds);
        }

        $filteredOrdersQuery = clone $ordersQuery;

        $orderTotals = (clone $filteredOrdersQuery)
            ->selectRaw('
                COUNT(*) as total_orders,
                COALESCE(SUM(total), 0) as total_amount,
                COALESCE(SUM(discount), 0) as total_discount,
                COALESCE(SUM(btw), 0) as total_btw
            ')
            ->first();

        $filteredOrderIds = (clone $filteredOrdersQuery)->select('id');

        $orderProductStats = DB::table('dashed__order_products')
            ->whereIn('order_id', $filteredOrderIds)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN sku = 'shipping_costs' THEN price ELSE 0 END), 0) as shipping_costs,
                COALESCE(SUM(CASE WHEN sku = 'payment_costs' THEN price ELSE 0 END), 0) as payment_costs,
                COALESCE(SUM(CASE WHEN sku NOT IN ('product_costs', 'shipping_costs', 'payment_costs') THEN quantity ELSE 0 END), 0) as products_sold
            ")
            ->first();

        $graphRows = (clone $filteredOrdersQuery)
            ->selectRaw('DATE(created_at) as date, COALESCE(SUM(total), 0) as total_amount')
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
            $graphValues[] = (float) ($graphRows[$dateKey]->total_amount ?? 0);

            $cursor->addDay();
        }

        $totalOrderCount = (int) ($orderTotals->total_orders ?? 0);
        $totalAmount = (float) ($orderTotals->total_amount ?? 0);
        $averageOrderAmount = $totalOrderCount > 0
            ? $totalAmount / $totalOrderCount
            : 0;

        $statistics = [
            'ordersAmount' => $totalOrderCount,
            'orderAmount' => CurrencyHelper::formatPrice($totalAmount),
            'paymentCostsAmount' => CurrencyHelper::formatPrice((float) ($orderProductStats->payment_costs ?? 0)),
            'shippingCostsAmount' => CurrencyHelper::formatPrice((float) ($orderProductStats->shipping_costs ?? 0)),
            'discountAmount' => CurrencyHelper::formatPrice((float) ($orderTotals->total_discount ?? 0)),
            'btwAmount' => CurrencyHelper::formatPrice((float) ($orderTotals->total_btw ?? 0)),
            'averageOrderAmount' => CurrencyHelper::formatPrice($averageOrderAmount),
            'productsSold' => (int) ($orderProductStats->products_sold ?? 0),
        ];

        $this->graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Omzet',
                        'data' => $graphValues,
                        'backgroundColor' => 'orange',
                        'borderColor' => 'orange',
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graphLabels,
            ],
            'data' => $statistics,
            'filters' => [
                'beginDate' => $beginDate->toDateTimeString(),
                'endDate' => $endDate->toDateTimeString(),
                'status' => $status,
                'paymentMethod' => $paymentMethod,
                'fulfillmentStatus' => $fulfillmentStatus,
                'retourStatus' => $retourStatus,
                'orderOrigin' => $orderOrigin,
            ],
        ];

        $this->dispatch('updateGraphData', $this->graphData);
    }

    protected function getFooterWidgets(): array
    {
        return [
            RevenueChart::class,
            RevenueCards::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
