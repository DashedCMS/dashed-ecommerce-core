<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Dashed\DashedEcommerceCore\Jobs\ExportInvoicesJob;

class ExportInvoicesPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer facturen';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer facturen';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'sort' => 'merged',
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
                        Select::make('sort')
                            ->label('Soort export')
                            ->options([
                                'merged' => 'Alle facturen in 1 PDF',
                                'combined' => 'Alle orders in 1 factuur',
                            ])
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function submit()
    {
        ExportInvoicesJob::dispatch($this->form->getState()['start_date'], $this->form->getState()['end_date'], $this->form->getState()['sort'], auth()->user()->email);

        Notification::make()
            ->title('De export wordt klaargemaakt en naar je toe gemaild')
            ->success()
            ->send();
    }
}
