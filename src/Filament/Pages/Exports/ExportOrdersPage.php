<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Jobs\ExportOrdersJob;
use Dashed\DashedEcommerceCore\Filament\Pages\Exports\Concerns\HasDateRangePresets;

class ExportOrdersPage extends Page
{
    use HasDateRangePresets;

    protected string $startDateField = 'startDate';
    protected string $endDateField = 'endDate';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer bestellingen';
    protected static string | UnitEnum | null $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer bestellingen';
    protected static ?int $navigationSort = 100000;

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->can('view_exports');
    }

    protected string $view = 'dashed-ecommerce-core::exports.pages.export';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'normal',
            'startDate' => null,
            'endDate' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Exporteer')->columnSpanFull()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start datum')
                            ->nullable(),
                        DatePicker::make('endDate')
                            ->label('Eind datum')
                            ->nullable()
                            ->afterOrEqual('startDate'),
                        Select::make('type')
                            ->label('Type export')
                            ->options([
                                'normal' => 'Normaal',
                                'perInvoiceLine' => 'Per factuurregel',
                            ])
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        ExportOrdersJob::dispatch(
            $this->form->getState()['startDate'],
            $this->form->getState()['endDate'],
            $this->form->getState()['type'],
            auth()->user()->email,
            auth()->id(),
        );
        Notification::make()
            ->title('De export wordt klaargemaakt en naar je toe gemaild')
            ->success()
            ->send();
    }
}
