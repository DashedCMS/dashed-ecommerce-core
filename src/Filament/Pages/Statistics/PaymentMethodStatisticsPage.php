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
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedCore\Filament\Support\ResourceFilterUrl;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Services\Statistics\PaymentMethodStatistics;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\PaymentMethodCards;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\PaymentMethodChart;
use Dashed\DashedEcommerceCore\Filament\Widgets\Statistics\PaymentMethodTable;

/**
 * Betaalpogingen per betaalmethode: hoe vaak geprobeerd, betaald en afgehaakt.
 * De telling zelf zit in PaymentMethodStatistics; deze pagina zet alleen de
 * filters om en deelt het resultaat met de drie widgets.
 */
class PaymentMethodStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-credit-card';
    protected static string | UnitEnum | null $navigationGroup = 'Statistieken';
    protected static ?int $navigationSort = 100000;

    protected string $view = 'dashed-ecommerce-core::statistics.pages.payment-method-statistics';

    /** Alle waarden van het statusfilter in de orderlijst. */
    private const ALL_ORDER_STATUSES = ['paid', 'partially_paid', 'waiting_for_confirmation', 'pending', 'concept', 'cancelled', 'return'];

    public ?array $data = [];

    public array $graphData = [];

    public static function getNavigationLabel(): string
    {
        return __('Betaalmethode statistieken');
    }

    public function getTitle(): string
    {
        return __('Betaalmethode statistieken');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_statistics');
    }

    public function mount(): void
    {
        $this->form->fill([
            'startDate' => now()->subMonth(),
            'endDate' => now(),
            'origins' => [],
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

        $start = ! empty($state['startDate']) ? Carbon::parse($state['startDate'])->startOfDay() : now()->subMonth()->startOfDay();
        $end = ! empty($state['endDate']) ? Carbon::parse($state['endDate'])->endOfDay() : now()->endOfDay();
        $origins = array_values(array_filter((array) ($state['origins'] ?? [])));

        $statistics = new PaymentMethodStatistics($start, $end, $origins);
        $rows = $statistics->rows();
        $perStep = $statistics->perStep($rows);

        $this->graphData = [
            'totals' => $statistics->totals($rows),
            'rows' => array_map(fn (array $row) => $row + $this->drillDownUrls($row, $start, $end, $origins), $rows),
            'graph' => [
                'labels' => $perStep['labels'],
                'datasets' => array_map(fn (array $series, int $index) => [
                    'label' => $series['name'],
                    'data' => $series['data'],
                    'borderColor' => self::color($index),
                    'backgroundColor' => self::color($index),
                    'tension' => 0.3,
                ], $perStep['series'], array_keys($perStep['series'])),
            ],
            'step' => $perStep['step'],
        ];

        $this->dispatch('updateGraphData', $this->graphData);
    }

    /**
     * Doorklikken naar de orderlijst: betaald via het betaalmethodefilter,
     * mislukt via het filter op mislukte betalingen. Alleen voor een methode
     * met een id; een oude naam zonder methode heeft niets om op te filteren.
     *
     * @return array{paid_url: ?string, failed_url: ?string}
     */
    private function drillDownUrls(array $row, Carbon $start, Carbon $end, array $origins): array
    {
        if (! $row['id']) {
            return ['paid_url' => null, 'failed_url' => null];
        }

        $shared = array_filter([
            'start_date' => ['start_date' => $start->toDateString()],
            'end_date' => ['end_date' => $end->toDateString()],
            'order_origin' => $origins !== [] ? ['values' => $origins] : null,
        ]);

        return [
            'paid_url' => ResourceFilterUrl::for(OrderResource::class, $shared + ['payment_method' => ['values' => [$row['id']]]]),
            // De orderlijst filtert standaard op betaalde orders; een mislukte
            // poging hangt juist vaak aan een geannuleerde of open order.
            'failed_url' => ResourceFilterUrl::for(OrderResource::class, $shared + [
                'failed_payment_method' => ['values' => [$row['id']]],
                'status' => ['values' => self::ALL_ORDER_STATUSES],
            ]),
        ];
    }

    private static function color(int $index): string
    {
        return ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#0891b2'][$index % 6];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label(__('Start datum'))
                            ->reactive(),
                        DatePicker::make('endDate')
                            ->label(__('Eind datum'))
                            ->after('startDate')
                            ->reactive(),
                        Select::make('origins')
                            ->label(__('Herkomst'))
                            ->multiple()
                            ->placeholder(__('Alles'))
                            ->options(fn () => Order::query()
                                ->toBase()
                                ->whereNotNull('order_origin')
                                ->distinct()
                                ->orderBy('order_origin')
                                ->pluck('order_origin')
                                ->mapWithKeys(fn ($origin) => [$origin => ucfirst($origin)])
                                ->all())
                            ->reactive(),
                    ])
                    ->columns([
                        'default' => 1,
                        'lg' => 3,
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFooterWidgets(): array
    {
        return [
            PaymentMethodCards::class,
            PaymentMethodChart::class,
            PaymentMethodTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'graphData' => $this->graphData,
        ];
    }
}
