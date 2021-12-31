<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Statistics;

use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Forms\Components\Card;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceEcommerceCore\Classes\CurrencyHelper;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;
use Qubiqx\QcommerceEcommerceCore\Models\OrderProduct;
use Qubiqx\QcommerceEcommerceCore\Models\PaymentMethod;

class ProductStatisticsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Product statistieken';
    protected static ?string $navigationGroup = 'Statistics';
    protected static ?string $title = 'Product statistieken';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'qcommerce-ecommerce-core::statistics.pages.product-statistics';

    public $search;
    public $startDate;
    public $endDate;

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subMonth(),
            'endDate' => now(),
        ]);
    }

    public function getStatisticsProperty()
    {
        $beginDate = $this->startDate ? Carbon::parse($this->startDate) : now()->subMonth();
        $endDate = $this->endDate ? Carbon::parse($this->endDate) : now()->addDay();

        $search = $this->search;
        $products = Product::notParentProduct()
            ->whereRaw('LOWER(name) like ?', '%' . strtolower($search) . '%')
            ->orWhereRaw('LOWER(content) like ?', '%' . strtolower($search) . '%')
            ->orWhereRaw('LOWER(short_description) like ?', '%' . strtolower($search) . '%')
            ->orWhereRaw('LOWER(description) like ?', '%' . strtolower($search) . '%')
            ->orWhereRaw('LOWER(search_terms) like ?', '%' . strtolower($search) . '%')
            ->orWhere('slug', 'LIKE', "%$search%")
            ->orWhere('weight', 'LIKE', "%$search%")
            ->orWhere('length', 'LIKE', "%$search%")
            ->orWhere('width', 'LIKE', "%$search%")
            ->orWhere('height', 'LIKE', "%$search%")
            ->orWhere('price', 'LIKE', "%$search%")
            ->orWhere('new_price', 'LIKE', "%$search%")
            ->orWhere('sku', 'LIKE', "%$search%")
            ->orWhere('ean', 'LIKE', "%$search%")
            ->orWhere('meta_title', 'LIKE', "%$search%")
            ->orWhere('meta_description', 'LIKE', "%$search%")
            ->latest()
            ->get();

        $orderIds = Order::isPaid()
            ->where('created_at', '>=', $beginDate)
            ->where('created_at', '<=', $endDate)
            ->pluck('id');

        $orderProducts = OrderProduct::whereIn('order_id', $orderIds)->get();

        $totalQuantitySold = 0;
        $totalAmountSold = 0;
        $averageCostPerProduct = 0;

        foreach ($products as $product) {
            $product->quantitySold = $orderProducts->where('product_id', $product->id)->sum('quantity');
            $product->amountSold = $orderProducts->where('product_id', $product->id)->sum('price');
            $totalQuantitySold += $product->quantitySold;
            $totalAmountSold += $product->amountSold;
            $product->amountSold = CurrencyHelper::formatPrice($product->amountSold);
            $product->currentStock = $product->use_stock ? $product->stock : ($product->stock_status == 'in_stock' ? 100000 : 0);
        }

        if ($totalQuantitySold) {
            $averageCostPerProduct = $totalAmountSold / $totalQuantitySold;
        }

        $statistics = [
            'totalQuantitySold' => $totalQuantitySold,
            'totalAmountSold' => CurrencyHelper::formatPrice($totalAmountSold),
            'averageCostPerProduct' => CurrencyHelper::formatPrice($averageCostPerProduct),
        ];

        $graph = [];

        $graphBeginDate = $beginDate->copy();
        while ($graphBeginDate < $endDate) {
            $graph['data'][] = OrderProduct::whereIn('id', $orderProducts->pluck('id'))->whereIn('product_id', $products->pluck('id'))->where('created_at', '>=', $graphBeginDate->copy()->startOfDay())->where('created_at', '<=', $graphBeginDate->copy()->endOfDay())->sum('quantity');
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
                        'borderColor' => "red",
                        'fill' => 'start',
                    ],
                ],
                'labels' => $graph['labels'],
            ],
            'data' => $statistics,
            'products' => $products,
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
                    TextInput::make('search')
                        ->label('Zoekterm')
                        ->reactive(),
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
                ])
                ->columns([
                    'default' => 1,
                    'lg' => 3,
                ]),
        ];
    }

    public function submit()
    {
        $this->notify('success', 'De export is gedownload');
    }
}
