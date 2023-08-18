<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Dashed\DashedEcommerceCore\Jobs\ExportOrdersJob;
use Dashed\DashedEcommerceCore\Jobs\ExportProductsJob;
use Filament\Pages\Page;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Exports\ProductListExport;

class ExportProductsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-download';
    protected static ?string $navigationLabel = 'Exporteer producten';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer producten';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export-products';

    protected function getFormSchema(): array
    {
        return [
        ];
    }

    public function submit()
    {
        ExportProductsJob::dispatch(auth()->user()->email);
        $this->notify('success', 'De export wordt klaargemaakt en naar je toe gemaild');
    }
}
