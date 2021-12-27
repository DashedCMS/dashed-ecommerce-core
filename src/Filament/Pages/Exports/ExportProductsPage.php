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
use Qubiqx\QcommerceEcommerceCore\Exports\ProductListExport;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

class ExportProductsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-download';
    protected static ?string $navigationLabel = 'Exporteer producten';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer producten';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'qcommerce-ecommerce-core::exports.pages.export-products';

    protected function getFormSchema(): array
    {
        return [
        ];
    }

    public function submit()
    {
        $products = Product::notParentProduct()
            ->search()
            ->latest()
            ->get();

        Excel::store(new ProductListExport($products), '/exports/product-lists/product-list.xlsx');

        $this->notify('success', 'De export is gedownload');
        return Storage::download('/exports/product-lists/product-list.xlsx');
    }
}
