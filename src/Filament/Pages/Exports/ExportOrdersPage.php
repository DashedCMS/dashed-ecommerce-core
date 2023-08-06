<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Carbon\Carbon;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Exports\OrderListExport;
use Dashed\DashedEcommerceCore\Exports\OrderListPerInvoiceLineExport;

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
        $orders = Order::isPaidOrReturn();

        if ($this->form->getState()['startDate']) {
            $orders->where('created_at', '>=', Carbon::parse($this->form->getState()['startDate'])->startOfDay());
        }

        if ($this->form->getState()['endDate']) {
            $orders->where('created_at', '<=', Carbon::parse($this->form->getState()['endDate'])->endOfDay());
        }

        $orders = $orders->get();

        if ($this->form->getState()['type'] == 'normal') {
            Excel::store(new OrderListExport($orders), '/exports/order-lists/order-list.xlsx');
        } elseif ($this->form->getState()['type'] == 'perInvoiceLine') {
            Excel::store(new OrderListPerInvoiceLineExport($orders), '/exports/order-lists/order-list.xlsx');
        }

        $this->notify('success', 'De export is gedownload');

        return Storage::download('/exports/order-lists/order-list.xlsx');
    }
}
