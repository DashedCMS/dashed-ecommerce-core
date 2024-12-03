<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Jobs\ExportOrdersJob;

class ExportOrdersPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer bestellingen';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer bestellingen';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'normal',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Exporteer')
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
        ExportOrdersJob::dispatch($this->form->getState()['startDate'], $this->form->getState()['endDate'], $this->form->getState()['type'], auth()->user()->email);
        Notification::make()
            ->title('De export wordt klaargemaakt en naar je toe gemaild')
            ->success()
            ->send();
    }
}
