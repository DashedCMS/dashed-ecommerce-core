<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Statistics;

use UnitEnum;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\RevenueChart;

class RevenueStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Omzet statistieken';

    protected static string|UnitEnum|null $navigationGroup = 'Statistieken';

    protected static ?string $title = 'Omzet statistieken';

    protected static ?int $navigationSort = 100000;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_statistics');
    }

    protected string $view = 'dashed-ecommerce-core::statistics.pages.revenue-statistics';

    /**
     * Bovengrens op het aantal punten in de grafiek. Per uur over een jaar zijn
     * dat er bijna negenduizend: onleesbaar in de grafiek en genoeg om de
     * browser te laten hangen. Liever een afgekapte lijn dan een pagina die
     * niets meer teruggeeft.
     */
    protected const MAX_GRAPH_POINTS = 750;

    public ?array $data = [];

    public array $graphData = [];

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'this_month',
            'steps' => 'per_day',
            'status' => 'payment_obligation',
        ]);
        $this->calculateStatistics();
    }

    public function setPeriod(string $period): void
    {
        $defaultData = Dashboard::getDefaultDataByPeriod($period);
        $this->data['startDate'] = $defaultData['startDate'];
        $this->data['endDate'] = $defaultData['endDate'];
        $this->data['period'] = $defaultData['period'];
        $this->data['steps'] = $defaultData['steps'];
        $this->form->fill($this->data);
        $this->calculateStatistics();
    }

    public function updated(string $propertyName): void
    {
        // De periode zet zelf de start- en einddatum en rekent daarna opnieuw.
        // Zou dat hier ook gebeuren, dan telt die berekening met de datums van
        // voor de wijziging, en dat was precies de fout: na het kiezen van
        // "vorige maand" stonden de datumvelden goed maar ging de grafiek over
        // de afgelopen maand vanaf vandaag.
        if ($propertyName === 'data.period') {
            return;
        }

        if (str_starts_with($propertyName, 'data.')) {
            $this->calculateStatistics();
        }
    }

    /**
     * De keuzelijsten met betaalmethodes en herkomsten. Twee daarvan zijn een
     * DISTINCT over de volledige betalings- en bestellingentabel, en form()
     * draait bij elke Livewire-ronde opnieuw: zonder deze cache betaalt elke
     * filterwijziging twee volledige scans voordat er ook maar iets gerekend is.
     *
     * @return array<string, array<string, string>>
     */
    protected function filterOptions(): array
    {
        $siteId = (string) Sites::getActive();

        return Cache::remember(
            "dashed.revenue-statistics.filter-options.{$siteId}",
            now()->addMinutes(10),
            fn (): array => [
                'paymentMethods' => PaymentMethod::query()
                    ->pluck('name', 'id')
                    ->toArray(),
                'legacyPaymentMethods' => OrderPayment::query()
                    ->whereNotNull('payment_method')
                    ->distinct()
                    ->pluck('payment_method')
                    ->filter()
                    ->unique()
                    ->mapWithKeys(fn ($paymentMethod) => [$paymentMethod => $paymentMethod])
                    ->toArray(),
                'orderOrigins' => Order::query()
                    ->whereNotNull('order_origin')
                    ->distinct()
                    ->pluck('order_origin')
                    ->filter()
                    ->unique()
                    ->mapWithKeys(fn ($orderOrigin) => [$orderOrigin => ucfirst($orderOrigin)])
                    ->toArray(),
            ]
        );
    }

    public function form(Schema $schema): Schema
    {
        $options = $this->filterOptions();
        $paymentMethods = $options['paymentMethods'];
        $legacyPaymentMethods = $options['legacyPaymentMethods'];
        $orderOrigins = $options['orderOrigins'];

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('period')
                            ->label(__('Periode'))
                            ->options(Dashboard::getPeriodOptions())
                            ->default('this_month')
                            ->reactive()
                            ->afterStateUpdated(function (callable $set, $state) {
                                $defaultData = Dashboard::getDefaultDataByPeriod($state);
                                $set('startDate', $defaultData['startDate']);
                                $set('endDate', $defaultData['endDate']);
                                $set('steps', $defaultData['steps']);

                                $this->calculateStatistics();
                            }),

                        Select::make('steps')
                            ->label(__('Stappen'))
                            ->options([
                                'per_hour' => __('Per uur'),
                                'per_day' => __('Per dag'),
                                'per_week' => __('Per week'),
                                'per_month' => __('Per maand'),
                                'per_quarter' => __('Per kwartaal'),
                                'per_year' => __('Per jaar'),
                            ])
                            ->default('per_day')
                            ->reactive(),

                        DatePicker::make('startDate')
                            ->label(__('Start datum'))
                            ->default(now()->startOfMonth())
                            ->reactive(),

                        DatePicker::make('endDate')
                            ->label(__('Eind datum'))
                            ->nullable()
                            // Gelijk aan de startdatum mag: een enkele dag
                            // bekijken is de normaalste vraag die er is. Met
                            // after() faalde de validatie stilletjes en bleef de
                            // grafiek op de vorige periode staan.
                            ->afterOrEqual('startDate')
                            ->default(now()->endOfMonth())
                            ->reactive(),

                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'all' => __('Alles'),
                                'payment_obligation' => __('Betalingsverplichting'),
                                'paid' => __('Betaald'),
                                'waiting_for_confirmation' => __('Wachten op bevestiging'),
                                'pending' => __('Lopende aankoop'),
                                'cancelled' => __('Geannuleerd'),
                                'return' => __('Retour'),
                            ])
                            ->default('payment_obligation')
                            ->reactive(),

                        Select::make('paymentMethod')
                            ->label(__('Betalingsmethode'))
                            ->options(array_merge([
                                'all' => 'Alles',
                            ], $paymentMethods, $legacyPaymentMethods))
                            ->default('all')
                            ->reactive(),

                        Select::make('fulfillmentStatus')
                            ->label(__('Fulfillment status'))
                            ->options([
                                'all' => __('Alles'),
                                'handled' => __('Afgehandeld'),
                                'unhandled' => __('Niet afgehandeld'),
                            ])
                            ->default('all')
                            ->reactive(),

                        Select::make('retourStatus')
                            ->label(__('Retour status'))
                            ->options([
                                'all' => __('Alles'),
                                'handled' => __('Afgehandeld'),
                                'unhandled' => __('Niet afgehandeld'),
                                'received' => __('Ontvangen'),
                                'shipped' => __('Onderweg'),
                                'waiting_for_return' => __('Wachten op retour'),
                            ])
                            ->default('all')
                            ->reactive(),

                        Select::make('orderOrigin')
                            ->label(__('Bestellings herkomst'))
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
     * In welke stap valt dit tijdstip. De grenzen lopen oplopend, dus dat is een
     * binaire zoekactie: bij tienduizenden orders is een lus door alle stappen
     * per order net zo goed een reden om te wachten als de queries van hiervoor.
     *
     * @param array<int, int> $boundaries
     */
    protected function stepIndexFor(int $timestamp, array $boundaries): ?int
    {
        $low = 0;
        $high = count($boundaries) - 1;

        if ($high < 0 || $timestamp < $boundaries[0]) {
            return null;
        }

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($boundaries[$middle] <= $timestamp) {
                $low = $middle;
            } else {
                $high = $middle - 1;
            }
        }

        return $low;
    }

    /** Een label dat bij de gekozen stap past; alles op d-m-Y gaf per uur zes keer dezelfde tekst. */
    protected function graphLabel(Carbon $start, string $steps): string
    {
        return match ($steps) {
            'per_hour' => $start->format('d-m H:00'),
            'per_week' => __('week :week', ['week' => $start->isoWeek()]) . $start->format(' (d-m)'),
            'per_month' => $start->format('m-Y'),
            'per_quarter' => 'Q' . $start->quarter . $start->format(' Y'),
            'per_year' => $start->format('Y'),
            default => $start->format('d-m-Y'),
        };
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

        $steps = $state['steps'] ?? 'per_day';
        $status = $state['status'] ?? 'payment_obligation';
        $paymentMethod = $state['paymentMethod'] ?? 'all';
        $fulfillmentStatus = $state['fulfillmentStatus'] ?? 'all';
        $retourStatus = $state['retourStatus'] ?? 'all';
        $orderOrigin = $state['orderOrigin'] ?? 'all';

        $formats = Dashboard::getFormatsByStep($steps);
        $startFormat = $formats['startFormat'];
        $endFormat = $formats['endFormat'];
        $addFormat = $formats['addFormat'];

        $ordersQuery = Order::query()
            ->whereBetween('created_at', [$beginDate, $endDate]);

        if ($status === 'payment_obligation') {
            // Betaalde orders inclusief retouren (negatieve credit-orders), zodat
            // de omzet netto is en aansluit op de financiele/verzamelfactuur-export.
            $ordersQuery->isPaidOrReturn();
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

        // De grafiek haalt zijn cijfers in een enkele query op en verdeelt ze
        // daarna in PHP over de stappen. Een som per stap was een query per punt
        // op de lijn: een jaar per dag is 365 keer dezelfde gefilterde query,
        // inclusief de subquery op betaalmethode, en per uur over een maand
        // ruim zevenhonderd. Dat is waar de pagina op vastliep.
        $starts = [];
        $cursor = $beginDate->copy()->$startFormat();
        $end = $endDate->copy()->$endFormat();

        while ($cursor->lte($end) && count($starts) < self::MAX_GRAPH_POINTS) {
            $starts[] = $cursor->copy()->$startFormat();
            $cursor->$addFormat();
        }

        $boundaries = array_map(fn (Carbon $start): int => $start->getTimestamp(), $starts);
        $totalsPerStep = array_fill(0, count($starts), 0.0);
        $lastBoundary = $endDate->getTimestamp();

        // Kale rijen, geen Eloquent-modellen: bij een jaar omzet scheelt dat
        // tienduizenden objecten die alleen maar opgeteld hoeven te worden.
        (clone $filteredOrdersQuery)
            ->toBase()
            ->select(['id', 'created_at', 'total'])
            ->orderBy('id')
            ->chunkById(5000, function ($rows) use (&$totalsPerStep, $boundaries, $lastBoundary) {
                foreach ($rows as $row) {
                    $timestamp = strtotime((string) $row->created_at);

                    if ($timestamp === false || $timestamp > $lastBoundary) {
                        continue;
                    }

                    $index = $this->stepIndexFor($timestamp, $boundaries);

                    if ($index === null) {
                        continue;
                    }

                    $totalsPerStep[$index] += (float) $row->total;
                }
            });

        $graphLabels = [];
        $graphValues = [];

        foreach ($starts as $index => $start) {
            $graphLabels[] = $this->graphLabel($start, $steps);
            $graphValues[] = round($totalsPerStep[$index], 2);
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
                'steps' => $steps,
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
