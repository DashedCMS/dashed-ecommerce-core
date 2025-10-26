<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Dashboard;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Illuminate\Contracts\Support\Htmlable;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    use BaseDashboard\Concerns\HasFiltersForm;

    protected static ?int $navigationSort = -2;

    public function getTitle(): string|Htmlable
    {
        return 'Dashboard van ' . Customsetting::get('site_name', null, 'DashedCMS');
    }

    //    protected string $view = 'dashed-ecommerce-core::dashboard.pages.dashboard';

    public array $data = [];

    public static function getStartData(): array
    {
        return [
            'startDate' => now()->subMonth()->format('d-m-Y'),
            'endDate' => now()->format('d-m-Y'),
            'period' => 'month',
            'steps' => 'per_day',
        ];
    }

    public static function getPeriodOptions(): array
    {
        return [
//                                'today' => 'Vandaag',
            'this_week' => 'Deze week',
            'week' => 'Afgelopen 7 dagen',
            'this_month' => 'Deze maand',
            'month' => 'Afgelopen 30 dagen',
            'this_year' => 'Dit jaar',
            'year' => 'Afgelopen jaar',
        ];
    }

    public function mount(): void
    {
        $this->data = self::getStartData();
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start datum')
                            ->default(now()->subMonth())
                            ->reactive()
                            ->maxDate(fn (callable $get) => $get('endDate') ?: now())
                            ->afterStateUpdated(function () {
                                $this->dispatch('setPageFiltersData', $this->data);
                            }),
                        DatePicker::make('endDate')
                            ->label('Eind datum')
                            ->minDate(fn (callable $get) => $get('startDate'))
                            ->default(now())
                            ->reactive()
                            ->afterStateUpdated(function () {
                                $this->dispatch('setPageFiltersData', $this->data);
                            }),
                        Select::make('period')
                            ->label('Periode')
                            ->reactive()
                            ->options(self::getPeriodOptions())
                            ->afterStateUpdated(function (callable $get, callable $set, $state) {
                                switch ($state) {
                                    //                                    case 'today':
                                    //                                        $set('startDate', now()->startOfDay());
                                    //                                        $set('endDate', now()->endOfDay());
                                    //                                        $set('steps', 'per_hour');
                                    //                                        break;
                                    case 'this_week':
                                        $set('startDate', now()->startOfWeek());
                                        $set('endDate', now()->endOfWeek());
                                        $set('steps', 'per_day');

                                        break;
                                    case 'week':
                                        $set('startDate', now()->subDays(7)->startOfDay());
                                        $set('endDate', now()->endOfDay());
                                        $set('steps', 'per_day');

                                        break;
                                    case 'this_month':
                                        $set('startDate', now()->startOfMonth());
                                        $set('endDate', now()->endOfMonth());
                                        $set('steps', 'per_day');

                                        break;
                                    case 'month':
                                        $set('startDate', now()->subDays(30)->startOfDay());
                                        $set('endDate', now()->endOfDay());
                                        $set('steps', 'per_day');

                                        break;
                                    case 'this_year':
                                        $set('startDate', now()->startOfYear());
                                        $set('endDate', now()->endOfYear());
                                        $set('steps', 'per_month');

                                        break;
                                    case 'year':
                                        $set('startDate', now()->subDays(365)->startOfDay());
                                        $set('endDate', now()->endOfDay());
                                        $set('steps', 'per_month');

                                        break;
                                }
                                $this->dispatch('setPageFiltersData', $this->data);
                            })
                            ->default('month'),
                        Select::make('steps')
                            ->label('Stappen')
                            ->reactive()
                            ->options([
                                'per_hour' => 'Per uur',
                                'per_day' => 'Per dag',
                                'per_week' => 'Per week',
                                'per_month' => 'Per maand',
                            ])
                            ->default('per_day')
                            ->afterStateUpdated(function () {
                                $this->dispatch('setPageFiltersData', $this->data);
                            }),
                    ])
                    ->columnSpanFull()
                    ->columns(4),
            ])
            ->statePath('data');
    }
}
