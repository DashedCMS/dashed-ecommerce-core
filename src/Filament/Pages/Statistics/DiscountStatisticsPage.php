<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

class DiscountStatisticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Korting statistieken';
    protected static ?string $navigationGroup = 'Statistics';
    protected static ?string $title = 'Korting statistieken';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::statistics.pages.discount-statistics';

    public $discountCode;
    public $status;
    public $startDate;
    public $endDate;

    public function mount(): void
    {
        $this->form->fill([
            'discountCode' => 'all',
            'status' => 'payment_obligation',
        ]);
    }

    public function getStatisticsProperty()
    {
        $beginDate = $this->startDate ? Carbon::parse($this->startDate) : now()->subMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->addDay();

        if ($this->discountCode && $this->discountCode != 'all') {
            $discountCode = DiscountCode::where('code', $this->discountCode)->first();
            if (! $discountCode) {
                $orders = Order::where('id', 0)->get();
            }
        }

        if (! isset($orders)) {
            $orders = Order::query()
                ->where('created_at', '>=', $beginDate)
                ->where('created_at', '<=', $endDate);

            if (isset($discountCode) && $discountCode) {
                $orders->where('discount_code_id', $discountCode->id);
            }

            if ($this->status == null || $this->status == 'payment_obligation') {
                $orders->isPaid();
            } elseif ($this->status != 'all') {
                $orders->where('status', $this->status);
            }

            $orders = $orders->latest()->get();
        }

        $totalOrderCount = $orders->count() ?? 0;
        $totalAmount = $orders->sum('total') ?? 0;
        $averageOrderAmount = $totalOrderCount > 0 ? ($totalAmount / $totalOrderCount) : 0;
        $discountAmount = $orders->sum('discount') ?? 0;
        $averageDiscountAmount = $discountAmount > 0 ? ($discountAmount / $totalOrderCount) : 0;

        $statistics = [
            'ordersAmount' => $totalOrderCount,
            'orderAmount' => CurrencyHelper::formatPrice($totalAmount),
            'discountAmount' => CurrencyHelper::formatPrice($discountAmount),
            'averageDiscountAmount' => CurrencyHelper::formatPrice($averageDiscountAmount),
            'averageOrderAmount' => CurrencyHelper::formatPrice($averageOrderAmount),
            'productsSold' => OrderProduct::whereIn('order_id', $orders->pluck('id'))->sum('quantity'),
        ];

        $graph = [];

        $graphBeginDate = $beginDate->copy();
        while ($graphBeginDate < $endDate) {
            $graph['data'][] = Order::whereIn('id', $orders->pluck('id') ?? [])->where('created_at', '>=', $graphBeginDate->copy()->startOfDay())->where('created_at', '<=', $graphBeginDate->copy()->endOfDay())->sum('discount');
            $graph['labels'][] = $graphBeginDate->format('d-m-Y');
            $graphBeginDate->addDay();
        }

        $graphData = [
            'graph' => [
                'datasets' => [
                    [
                        'label' => 'Stats',
                        'data' => $graph['data'],
                        'backgroundColor' => 'orange',
                        'borderColor' => "orange",
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graph['labels'],
            ],
            'data' => $statistics,
            'orders' => $orders,
        ];

        $this->emit('updatedStatistics', $graphData);

        return $graphData;
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
                    DatePicker::make('startDate')
                        ->label('Start datum')
                        ->reactive(),
                    DatePicker::make('endDate')
                        ->label('Eind datum')
                        ->rules([
                            'nullable',
                            'after:start_date',
                        ])
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
                    Select::make('discountCode')
                        ->label('Kortingscode')
                        ->options(array_merge([
                            'all' => 'Alles',
                        ], DiscountCode::pluck('name', 'code')->toArray()))
                        ->reactive(),
                ])
                ->columns([
                    'default' => 1,
                    'lg' => 4,
                ]),
        ];
    }

    public function submit()
    {
        $this->notify('success', 'De export is gedownload');
    }
}
