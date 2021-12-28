<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics;

use Carbon\Carbon;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceCore\Classes\Helper;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Models\PaymentMethod;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Exports\ProductListExport;

class RevenueStatisticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Omzet statistieken';
    protected static ?string $navigationGroup = 'Statistics';
    protected static ?string $title = 'Omzet statistieken';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'qcommerce-ecommerce-core::statistics.pages.revenue-statistics';

    public $status;
    public $paymentMethod;
    public $fulfillmentStatus;
    public $retourStatus;
    public $orderOrigin;
    public $startDate;
    public $endDate;

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
            'orderAmount' => Helper::formatPrice($totalAmount),
            'paymentCostsAmount' => Helper::formatPrice($paymentCosts),
            'shippingCostsAmount' => Helper::formatPrice($shippingCosts),
            'discountAmount' => Helper::formatPrice($orders->sum('discount')),
            'btwAmount' => Helper::formatPrice($orders->sum('btw')),
            'averageOrderAmount' => Helper::formatPrice($averageOrderAmount),
            'productsSold' => OrderProduct::whereIn('order_id', $orders->pluck('id'))->whereNotIn('sku', ['product_costs', 'shipping_costs'])->sum('quantity'),
        ];

        $graph = [];

        $graphBeginDate = $beginDate->copy();
        while ($graphBeginDate < $endDate) {
            $graph['data'][] = Order::whereIn('id', $orders->pluck('id'))->where('created_at', '>=', $graphBeginDate->copy()->startOfDay())->where('created_at', '<=', $graphBeginDate->copy()->endOfDay())->sum('total');
            $graph['labels'][] = $graphBeginDate->format('d-m-Y');
            $graphBeginDate->addDay();
        }

        return [
          'graph' => [
              'datasets' => [
                  [
                      'label' => 'Stats',
                      'data' => $graph['data'],
                      'backgroundColor' => 'orange',
                      'borderColor' => "red",
                      'fill' => 'start',
                  ]
              ],
              'labels' => $graph['labels']
          ],
          'data' => $statistics
        ];
    }

    protected function getFormSchema(): array
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

        return [
            Card::make()
                ->schema([
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
                    DatePicker::make('startDate')
                        ->label('Start datum')
                        ->reactive(),
                    DatePicker::make('endDate')
                        ->label('Eind datum')
                        ->rules([
                            'nullable',
                            'after:start_date'
                        ])
                        ->reactive(),
                ])
                ->columns([
                    'default' => 1,
                    'lg' => 7,
                ])
        ];
    }

    public function submit()
    {
        $this->notify('success', 'De export is gedownload');
    }
}
