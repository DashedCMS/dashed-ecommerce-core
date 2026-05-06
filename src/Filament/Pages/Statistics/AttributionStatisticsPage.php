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
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedCore\Filament\Pages\Dashboard\Dashboard;

/**
 * Filament-pagina onder Statistics met drie groupings (source / medium /
 * campaign) over de gekozen periode. Toont per groep aantal orders, totaal
 * omzet en gemiddeld bedrag, plus een totaal-regel met hoeveel orders in de
 * periode wel of geen UTM-source hadden.
 */
class AttributionStatisticsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Herkomst statistieken';
    protected static string|UnitEnum|null $navigationGroup = 'Statistics';
    protected static ?string $title = 'Herkomst statistieken';
    protected static ?int $navigationSort = 100100;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_statistics');
    }

    protected string $view = 'dashed-ecommerce-core::statistics.pages.attribution-statistics';

    public ?array $data = [];

    public array $stats = [];

    public function mount(): void
    {
        $this->form->fill([
            'period' => 'this_month',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'limit' => 10,
        ]);
        $this->calculateStatistics();
    }

    public function setPeriod(string $period): void
    {
        $defaultData = Dashboard::getDefaultDataByPeriod($period);
        $this->data['startDate'] = $defaultData['startDate'];
        $this->data['endDate'] = $defaultData['endDate'];
        $this->data['period'] = $defaultData['period'];
        $this->form->fill($this->data);
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
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Filters')
                    ->columns(2)
                    ->schema([
                        Select::make('period')
                            ->label('Periode')
                            ->live()
                            ->afterStateUpdated(function (?string $state) {
                                if ($state) {
                                    $this->setPeriod($state);
                                }
                            })
                            ->options([
                                'today' => 'Vandaag',
                                'yesterday' => 'Gisteren',
                                'this_week' => 'Deze week',
                                'last_week' => 'Vorige week',
                                'this_month' => 'Deze maand',
                                'last_month' => 'Vorige maand',
                                'this_year' => 'Dit jaar',
                                'last_year' => 'Vorig jaar',
                                'custom' => 'Handmatig',
                            ])
                            ->default('this_month'),
                        DatePicker::make('startDate')
                            ->label('Vanaf')
                            ->live(onBlur: true)
                            ->columnSpan(1),
                        DatePicker::make('endDate')
                            ->label('Tot')
                            ->live(onBlur: true)
                            ->columnSpan(1),
                        Select::make('utm_source')
                            ->label('Filter op bron')
                            ->placeholder('Alle bronnen')
                            ->live()
                            ->options(fn () => $this->distinctValues('utm_source'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('utm_medium')
                            ->label('Filter op medium')
                            ->placeholder('Alle mediums')
                            ->live()
                            ->options(fn () => $this->distinctValues('utm_medium'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('utm_campaign')
                            ->label('Filter op campagne')
                            ->placeholder('Alle campagnes')
                            ->live()
                            ->options(fn () => $this->distinctValues('utm_campaign'))
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Select::make('limit')
                            ->label('Toon maximaal')
                            ->live()
                            ->options([
                                10 => 'Top 10',
                                25 => 'Top 25',
                                50 => 'Top 50',
                                100 => 'Top 100',
                            ])
                            ->default(10),
                    ]),
            ]);
    }

    /**
     * @return array<string,string>
     */
    private function distinctValues(string $column): array
    {
        return Order::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->orderBy($column)
            ->pluck($column, $column)
            ->toArray();
    }

    protected function calculateStatistics(): void
    {
        $state = $this->form->getState();

        $start = ! empty($state['startDate'])
            ? Carbon::parse($state['startDate'])->startOfDay()
            : now()->startOfMonth();
        $end = ! empty($state['endDate'])
            ? Carbon::parse($state['endDate'])->endOfDay()
            : now()->endOfDay();

        $limit = (int) ($state['limit'] ?? 10);

        $base = Order::query()
            ->isPaid()
            ->thisSite()
            ->whereBetween('created_at', [$start, $end]);

        if (! empty($state['utm_source'])) {
            $base->where('utm_source', $state['utm_source']);
        }
        if (! empty($state['utm_medium'])) {
            $base->where('utm_medium', $state['utm_medium']);
        }
        if (! empty($state['utm_campaign'])) {
            $base->where('utm_campaign', $state['utm_campaign']);
        }

        $totalOrders = (clone $base)->count();
        $totalRevenue = (float) (clone $base)->sum('total');

        $withUtm = (clone $base)->whereNotNull('utm_source')->where('utm_source', '!=', '')->count();
        $withoutUtm = $totalOrders - $withUtm;

        $this->stats = [
            'period' => [
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ],
            'totals' => [
                'orders' => $totalOrders,
                'revenue' => CurrencyHelper::formatPrice($totalRevenue),
                'with_utm' => $withUtm,
                'without_utm' => $withoutUtm,
                'with_utm_percentage' => $totalOrders > 0 ? round(($withUtm / $totalOrders) * 100, 1) : 0,
            ],
            'by_source' => $this->groupBy('utm_source', $base, $limit, $totalRevenue),
            'by_medium' => $this->groupBy('utm_medium', $base, $limit, $totalRevenue),
            'by_campaign' => $this->groupBy('utm_campaign', $base, $limit, $totalRevenue),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groupBy(string $column, $base, int $limit, float $totalRevenue): array
    {
        return (clone $base)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select([
                $column . ' as label',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(total) as revenue'),
                DB::raw('AVG(total) as avg_order_value'),
            ])
            ->groupBy($column)
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label,
                'orders' => (int) $row->orders,
                'revenue' => CurrencyHelper::formatPrice((float) $row->revenue),
                'avg_order_value' => CurrencyHelper::formatPrice((float) $row->avg_order_value),
                'revenue_share' => $totalRevenue > 0
                    ? round(((float) $row->revenue / $totalRevenue) * 100, 1)
                    : 0,
            ])
            ->toArray();
    }
}
