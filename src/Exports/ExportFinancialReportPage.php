<?php

namespace Dashed\DashedEcommerceCore\Exports;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Jobs\ExportFinancialReportJob;

class ExportFinancialReportPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer financieel rapport';
    protected static string | UnitEnum | null $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer financieel rapport';
    protected static ?int $navigationSort = 100000;

    protected string $view = 'dashed-ecommerce-core::exports.pages.export';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now(),
            'end_date' => now(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Exporteer')->columnSpanFull()
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start datum'),
                        DatePicker::make('end_date')
                            ->label('Eind datum')
                            ->afterOrEqual('start_date'),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        ExportFinancialReportJob::dispatch($this->form->getState()['start_date'], $this->form->getState()['end_date'], auth()->user()->email);

        Notification::make()
            ->title('De export wordt klaargemaakt en naar je toe gemaild')
            ->success()
            ->send();
    }
}
