<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports;

use Carbon\Carbon;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Exports\OrderListExport;

class ExportOrdersPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-download';
    protected static ?string $navigationLabel = 'Exporteer bestellingen';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer bestellingen';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'qcommerce-ecommerce-core::exports.pages.export-orders';

    protected function getFormSchema(): array
    {
        return [
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
        ];
    }

    public function submit()
    {
        $orders = Order::isPaidOrReturn();

        if ($this->form->getState()['start_date'] != null) {
            $orders->where('created_at', '>=', Carbon::parse($this->form->getState()['start_date'])->startOfDay());
        }

        if ($this->form->getState()['end_date'] != null) {
            $orders->where('created_at', '<=', Carbon::parse($this->form->getState()['end_date'])->endOfDay());
        }

        $orders = $orders->get();

        Excel::store(new OrderListExport($orders), '/exports/order-lists/order-list.xlsx');

        $this->notify('success', 'De export is gedownload');

        return Storage::download('/exports/order-lists/order-list.xlsx');
    }
}
