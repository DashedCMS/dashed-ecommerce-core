<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Exports\PricePerProductForUserExport;
use Dashed\DashedEcommerceCore\Exports\ProductsToEdit;
use Dashed\DashedEcommerceCore\Imports\PricePerProductForUserImport;
use Dashed\DashedEcommerceCore\Imports\ProductsToEditImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListProducts extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getTableQuery(): ?Builder
    {
        return Product::query();
    }

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
            Action::make('export')
                ->label('Exporteer')
                ->hiddenLabel()
                ->icon('heroicon-s-arrow-down-tray')
                ->action(function () {
                    Notification::make()
                        ->title('Exporteren')
                        ->body('Het exporteren is gelukt.')
                        ->success()
                        ->send();

                    return Excel::download(new ProductsToEdit, 'Producten van ' . Customsetting::get('site_name') . '.xlsx');
                }),
            Action::make('import')
                ->label('Importeer')
                ->icon('heroicon-s-arrow-up-tray')
                ->hiddenLabel()
                ->form([
                    FileUpload::make('file')
                        ->label('Bestand')
                        ->disk('local')
                        ->directory('imports')
                        ->rules([
                            'required',
                            'file',
                            'mimes:csv,xlsx',
                        ]),
                ])
                ->action(function ($data) {

                    $file = Storage::disk('local')->path($data['file']);
                    Excel::import(new ProductsToEditImport, $file);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren is gelukt, refresh de pagina.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
