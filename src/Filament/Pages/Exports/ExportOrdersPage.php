<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Jobs\ExportOrdersJob;

class ExportOrdersPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-download';
    protected static ?string $navigationLabel = 'Exporteer bestellingen';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer bestellingen';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export-orders';

    public $startDate;
    public $endDate;
    public $type = 'normal';

    protected function getFormSchema(): array
    {
        return [
            Section::make('Exporteer')
            ->schema([
                DatePicker::make('startDate')
                    ->label('Start datum')
                    ->rules([
                        'nullable',
                    ]),
                DatePicker::make('endDate')
                    ->label('Eind datum')
                    ->rules([
                        'nullable',
                        'after:start_date',
                    ]),
                Select::make('type')
                    ->label('Type export')
                    ->options([
                        'normal' => 'Normaal',
                        'perInvoiceLine' => 'Per factuurregel',
                    ])
                    ->required()
                    ->rules([
                        'required',
                    ]),
            ]),

        ];
    }

    public function submit()
    {
        ExportOrdersJob::dispatch($this->form->getState()['startDate'], $this->form->getState()['endDate'], $this->form->getState()['type'], auth()->user()->email);
        $this->notify('success', 'De export wordt klaargemaakt en naar je toe gemaild');
    }
}
