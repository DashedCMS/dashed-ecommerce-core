<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Jobs\ExportInvoicesJob;

class ExportInvoicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-download';
    protected static ?string $navigationLabel = 'Exporteer facturen';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer facturen';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export-invoices';

    public function mount(): void
    {
        $this->form->fill([
            'sort' => 'merged',
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Exporteer')
            ->schema([
                DatePicker::make('start_date')
                    ->label('Start datum')
                    ->rules([
                        'nullable',
                    ]),
                DatePicker::make('end_date')
                    ->label('Eind datum')
                    ->rules([
                        'nullable',
                        'after:start_date',
                    ]),
                Select::make('sort')
                    ->label('Soort export')
                    ->options([
                        'merged' => 'Alle facturen in 1 PDF',
                        'combined' => 'Alle orders in 1 factuur',
                    ])
                    ->rules([
                        'required',
                    ])
                    ->required(),
            ]),

        ];
    }

    public function submit()
    {
        ExportInvoicesJob::dispatch($this->form->getState()['start_date'], $this->form->getState()['end_date'], $this->form->getState()['sort'], auth()->user()->email);
        $this->notify('success', 'De export wordt klaargemaakt en naar je toe gemaild');
    }
}
