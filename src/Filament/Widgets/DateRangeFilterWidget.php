<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets;

use Filament\Schemas\Schema;
use Filament\Widgets\Widget;

class DateRangeFilterWidget extends Widget
{
    protected static string $view = 'dashed-ecommerce-core::widgets.date-range-filter-widget';

    public static function canView(): bool
    {
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('start_date')
                    ->default(now()->subYear()->startOfDay())
                    ->reactive(),

                DatePicker::make('end_date')
                    ->default(now()->endOfDay())
                    ->reactive(),

                Select::make('period')
                    ->options([
                        'day' => 'Day',
                        'week' => 'Week',
                        'month' => 'Month',
                    ])
                    ->default('week')
                    ->reactive(),
            ])
            ->statePath('filters');
    }

    public function getFilters(): array
    {
        return $this->form->getState();
    }
}
