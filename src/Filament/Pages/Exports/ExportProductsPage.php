<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\Exports;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Jobs\ExportProductsJob;

class ExportProductsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'Exporteer producten';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer producten';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'dashed-ecommerce-core::exports.pages.export';

    public function submit()
    {
        ExportProductsJob::dispatch(auth()->user()->email);
        Notification::make()
            ->title('De export wordt klaargemaakt en naar je toe gemaild')
            ->success()
            ->send();
    }
}
