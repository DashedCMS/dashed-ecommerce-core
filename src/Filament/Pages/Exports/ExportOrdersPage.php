<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use LynX39\LaraPdfMerger\Facades\PdfMerger;
use Maatwebsite\Excel\Facades\Excel;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceEcommerceCore\Exports\OrderListExport;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

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
                    'nullable'
                ]),
            DatePicker::make('end_date')
                ->label('Eind datum')
                ->rules([
                    'nullable',
                    'after:start_date'
                ]),
        ];
    }

    public function submit()
    {
        $orders = Order::isPaidOrReturn();

        if (request()->get('beginDate') != null) {
            $orders->where('created_at', '>=', Carbon::parse(request()->get('beginDate'))->startOfDay());
        }

        if (request()->get('endDate') != null) {
            $orders->where('created_at', '<=', Carbon::parse(request()->get('endDate'))->endOfDay());
        }

        $orders = $orders->get();

        Excel::store(new OrderListExport($orders), '/exports/order-lists/order-list.xlsx');

        $this->notify('success', 'De export is gedownload');
        return Storage::download('/exports/order-lists/order-list.xlsx');
    }
}
