<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use UnitEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
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

    protected string $view = 'dashed-ecommerce-core::statistics.pages.revenue-statistics';

    /**
     * Form data (filters).
     */
    public ?array $data = [];

    /**
     * Graph + stats data voor widgets / JS.
     */
    public $graphData;

    public function mount(): void
    {
        // Init form met defaults
        $this->form->fill();

        $this->getStatisticsProperty();
    }

    public function updated(string $propertyName): void
    {
        // Alleen opnieuw rekenen als filters wijzigen
        if (str_starts_with($propertyName, 'data.')) {
            $this->getStatisticsProperty();
        }
    }

    /**
     * Filament v4 form schema via Schemas.
     */
    public function form(Schema $schema): Schema
    {
        $paymentMethods = [];

        foreach (PaymentMethod::get() as $paymentMethod) {
            $paymentMethods[$paymentMethod->id] = $paymentMethod->name;
        }

        foreach (
            OrderPayment::whereNotNull('payment_method')
                ->distinct('payment_method')
                ->pluck('payment_method')
                ->unique() as $paymentMethod
        ) {
            $paymentMethods[$paymentMethod] = $paymentMethod;
        }

        $orderOrigins = [];

        foreach (
            Order::whereNotNull('order_origin')
                ->distinct('order_origin')
                ->pluck('order_origin')
                ->unique() as $orderOrigin
        ) {
            $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
        }

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
                            ], $paymentMethods))
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

    /**
     * Zelfde logica als eerst, maar nu via $this->form->getState().
     */
    public function getStatisticsProperty(): void
    {
        $state = $this->form->getState();

        $beginDate = ! empty($state['startDate'])
            ? Carbon::parse($state['startDate'])
            : now()->subMonth();

        $endDate = ! empty($state['endDate'])
            ? Carbon::parse($state['endDate'])
            : now()->addDay();

        $status = $state['status'] ?? 'all';
        $paymentMethod = $state['paymentMethod'] ?? 'all';
        $fulfillmentStatus = $state['fulfillmentStatus'] ?? 'all';
        $retourStatus = $state['retourStatus'] ?? 'all';
        $orderOrigin = $state['orderOrigin'] ?? 'all';

        $ordersQuery = Order::query()
            ->with(['orderProducts'])
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate);

        if ($status === null || $status === 'payment_obligation') {
            $ordersQuery->isPaid();
        } elseif ($status !== 'all') {
            $ordersQuery->where('status', $status);
        }

        if ($fulfillmentStatus !== null && $fulfillmentStatus !== 'all') {
            $ordersQuery->where('fulfillment_status', $fulfillmentStatus);
        }

        if ($retourStatus !== null && $retourStatus !== 'all') {
            $ordersQuery->where('retour_status', $retourStatus);
        }

        if ($orderOrigin !== null && $orderOrigin !== 'all') {
            $ordersQuery->where('order_origin', $orderOrigin);
        }

        if ($paymentMethod !== null && $paymentMethod !== 'all') {
            $paymentMethodModel = PaymentMethod::find($paymentMethod);

            if ($paymentMethodModel) {
                $orderPayments = OrderPayment::where('payment_method_id', $paymentMethodModel->id)->pluck('order_id');
            } else {
                $orderPayments = OrderPayment::where('payment_method', $paymentMethod)->pluck('order_id');
            }

            $ordersQuery->whereIn('id', $orderPayments);
        }

        $orders = $ordersQuery->latest()->get();
        $orderIds = $orders->pluck('id');

        $shippingCosts = 0;
        $paymentCosts = 0;

        foreach ($orders as $order) {
            foreach ($order->orderProducts as $orderProduct) {
                if ($orderProduct->sku === 'shipping_costs') {
                    $shippingCosts += $orderProduct->price;
                } elseif ($orderProduct->sku === 'payment_costs') {
                    $paymentCosts += $orderProduct->price;
                }
            }
        }

        $totalOrderCount = $orders->count();
        $totalAmount = $orders->sum('total');
        $averageOrderAmount = $totalOrderCount > 0 ? ($totalAmount / $totalOrderCount) : 0;

        $statistics = [
            'ordersAmount' => $totalOrderCount,
            'orderAmount' => CurrencyHelper::formatPrice($totalAmount),
            'paymentCostsAmount' => CurrencyHelper::formatPrice($paymentCosts),
            'shippingCostsAmount' => CurrencyHelper::formatPrice($shippingCosts),
            'discountAmount' => CurrencyHelper::formatPrice($orders->sum('discount')),
            'btwAmount' => CurrencyHelper::formatPrice($orders->sum('btw')),
            'averageOrderAmount' => CurrencyHelper::formatPrice($averageOrderAmount),
            'productsSold' => OrderProduct::whereIn('order_id', $orderIds)
                ->whereNotIn('sku', ['product_costs', 'shipping_costs'])
                ->sum('quantity'),
        ];

        $graph = [
            'data' => [],
            'labels' => [],
        ];

        $graphBeginDate = $beginDate->copy();

        while ($graphBeginDate < $endDate) {
            $graph['data'][] = Order::whereIn('id', $orderIds)
                ->where('created_at', '>=', $graphBeginDate->copy()->startOfDay())
                ->where('created_at', '<=', $graphBeginDate->copy()->endOfDay())
                ->sum('total');

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
            'data' => $statistics,
        ];

        $this->graphData = $graphData;

        $this->dispatch('updateGraphData', $graphData);
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
