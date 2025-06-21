<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Dashed\DashedEcommerceCore\Imports\EANCodesToImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Forms\Components\Placeholder;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Exports\ProductsToEdit;
use Dashed\DashedEcommerceCore\Imports\ProductsToEditImport;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

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

                    return Excel::download(new ProductsToEdit(), 'Producten van ' . Customsetting::get('company_name') . '.xlsx');
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
                    Excel::import(new ProductsToEditImport(), $file);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren is gelukt, refresh de pagina.')
                        ->success()
                        ->send();
                }),
            Action::make('importEANCodes')
                ->label('Importeer EAN codes')
                ->icon('heroicon-s-qr-code')
                ->hiddenLabel()
                ->form([
                    Placeholder::make('placeholder')
                        ->label('Importeer EAN codes voor producten')
                        ->content('Gebruik een excel/csv bestand met 1 kolom met de EAN codes. Deze worden toegevoegd aan de producten zonder EAN code. Dit zijn er momenteel ' . Product::whereNull('ean')->count() . '. Maak een bestand met nooit meer dan he lege aantal EANs. De EAN codes dienen uniek te zijn.')
                        ->columnSpanFull(),
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
                    Excel::import(new EANCodesToImport(), $file);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren is gelukt, refresh de pagina.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
