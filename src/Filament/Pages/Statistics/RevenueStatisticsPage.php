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
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueChart;

class RevenueStatisticsPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Omzet statistieken';
    protected static string | UnitEnum | null $navigationGroup = 'Statistics';
    protected static ?string $title = 'Omzet statistieken';
    protected static ?int $navigationSort = 100000;

    protected string $view = 'dashed-ecommerce-core::statistics.pages.revenue-statistics';

    public $status;
    public $paymentMethod;
    public $fulfillmentStatus;
    public $retourStatus;
    public $orderOrigin;
    public $startDate;
    public $endDate;
    public $graphData;

    public function mount(): void
    {
        $this->form->fill([
            'status' => 'all',
            'paymentMethod' => 'all',
            'fulfillmentStatus' => 'all',
            'retourStatus' => 'all',
            'orderOrigin' => 'all',
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

        $orders = Order::query()
            ->search()
            ->with(['orderProducts'])
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate);

        if ($this->status == null || $this->status == 'payment_obligation') {
            $orders->isPaid();
        } elseif ($this->status != 'all') {
            $orders->where('status', $this->status);
        }

        if ($this->fulfillmentStatus != null && $this->fulfillmentStatus != 'all') {
            $orders->where('fulfillment_status', $this->fulfillmentStatus);
        }

        if ($this->retourStatus != null && $this->retourStatus != 'all') {
            $orders->where('retour_status', $this->retourStatus);
        }

        if ($this->orderOrigin != null && $this->orderOrigin != 'all') {
            $orders->where('order_origin', $this->orderOrigin);
        }

        if ($this->paymentMethod != null && $this->paymentMethod != 'all') {
            $paymentMethod = PaymentMethod::find($this->paymentMethod);
            if ($paymentMethod) {
                $orderPayments = OrderPayment::where('payment_method_id', $paymentMethod->id)->pluck('order_id');
            } else {
                $orderPayments = OrderPayment::where('payment_method', $this->paymentMethod)->pluck('order_id');
            }
            $orders->whereIn('id', $orderPayments);
        }

        $orders = $orders->latest()->get();

        $shippingCosts = 0;
        $paymentCosts = 0;
        foreach ($orders as $order) {
            foreach ($order->orderProducts as $orderProduct) {
                if ($orderProduct->sku == 'shipping_costs') {
                    $shippingCosts += $orderProduct->price;
                } elseif ($orderProduct->sku == 'payment_costs') {
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
            'productsSold' => OrderProduct::whereIn('order_id', $orders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
        ];

        $graph = [];

        $graphBeginDate = $beginDate->copy();
        while ($graphBeginDate < $endDate) {
            $graph['data'][] = Order::whereIn('id', $orders->pluck('id'))->where('created_at', '>=', $graphBeginDate->copy()->startOfDay())->where('created_at', '<=', $graphBeginDate->copy()->endOfDay())->sum('total');
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
                        'borderColor' => "orange",
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

    public function form(Schema $schema): Schema
    {
        $paymentMethods = [];
        foreach (PaymentMethod::get() as $paymentMethod) {
            $paymentMethods[$paymentMethod->id] = $paymentMethod->name;
        }

        foreach (OrderPayment::whereNotNull('payment_method')->distinct('payment_method')->pluck('payment_method')->unique() as $paymentMethod) {
            $paymentMethods[$paymentMethod] = $paymentMethod;
        }

        $orderOrigins = [];
        foreach (Order::whereNotNull('order_origin')->distinct('order_origin')->pluck('order_origin')->unique() as $orderOrigin) {
            $orderOrigins[$orderOrigin] = ucfirst($orderOrigin);
        }

        return $schema->schema([
            Section::make()->columnSpanFull()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('Start datum')
                        ->reactive(),
                    DatePicker::make('endDate')
                        ->label('Eind datum')
                        ->nullable()
                        ->after('startDate')
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
                        ->reactive(),
                    Select::make('paymentMethod')
                        ->label('Betalingsmethode')
                        ->options(array_merge([
                            'all' => 'Alles',
                        ], $paymentMethods))
                        ->reactive(),
                    Select::make('fulfillmentStatus')
                        ->label('Fulfillment status')
                        ->options([
                            'all' => 'Alles',
                            'handled' => 'Afgehandeld',
                            'unhandled' => 'Niet afgehandeld',
                        ])
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
                        ->reactive(),
                    Select::make('orderOrigin')
                        ->label('Bestellings herkomst')
                        ->options(array_merge([
                            'all' => 'Alles',
                        ], $orderOrigins))
                        ->reactive(),
                ])
                ->columns([
                    'default' => 1,
                    'sm' => 2,
                    'md' => 3,
                    'lg' => 4,
                ]),
        ]);
    }

    protected function getFooterWidgets(): array
    {
        return [
            RevenueChart::make(),
            RevenueCards::make(),
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
