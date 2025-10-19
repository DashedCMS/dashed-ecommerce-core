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
use Dashed\DashedEcommerceCore\Jobs\ExportInvoicesJob;

class ExportInvoicesPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer facturen';
    protected static string | UnitEnum | null $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer facturen';
    protected static ?int $navigationSort = 100000;

    protected string $view = 'dashed-ecommerce-core::exports.pages.export';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'sort' => 'merged',
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
