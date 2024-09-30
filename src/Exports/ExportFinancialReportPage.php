<?php

namespace Dashed\DashedEcommerceCore\Exports;

use Dashed\DashedEcommerceCore\Jobs\ExportFinancialReportJob;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Jobs\ExportInvoicesJob;

class ExportFinancialReportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer financieel rapport';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer financieel rapport';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now(),
            'end_date' => now(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Exporteer')
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
