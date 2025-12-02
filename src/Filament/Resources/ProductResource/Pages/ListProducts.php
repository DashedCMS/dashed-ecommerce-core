<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Imports\EANCodesImporter;
use Filament\Actions\Action;
use Filament\Actions\ImportAction;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Models\Customsetting;
use Filament\Infolists\Components\TextEntry;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Jobs\ImportEANCodes;
use Dashed\DashedEcommerceCore\Exports\ProductsToEdit;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedEcommerceCore\Jobs\ImportProductToEditJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListProducts extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected Width|string|null $maxContentWidth = 'full';

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

                    return Excel::download(new ProductsToEdit(), 'Producten van ' . Customsetting::get('site_name') . '.xlsx');
                }),
            Action::make('import')
                ->label('Importeer')
                ->icon('heroicon-s-arrow-up-tray')
                ->hiddenLabel()
                ->schema([
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

                    ImportProductToEditJob::dispatch($data['file']);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren wordt op de achtergrond uitgevoerd.')
                        ->success()
                        ->send();
                }),
//            ImportAction::make()
//                ->importer(EANCodesImporter::class)
//                ->label('Importeer EAN codes')
//                ->modelLabel('EAN codes')
////                ->getCustomModalHeading('test')
////                ->help('Gebruik een excel/csv bestand met 1 kolom met de EAN codes. Deze worden toegevoegd aan de producten zonder EAN code. Dit zijn er momenteel ' . Product::whereNull('ean')->count() . '. Maak een bestand met nooit meer dan he lege aantal EANs. De EAN codes dienen uniek te zijn.')
//                ->icon('heroicon-s-qr-code')
//                ->color('primary')
//                ->hiddenLabel(),
            Action::make('importEANCodes')
                ->label('Importeer EAN codes')
                ->icon('heroicon-s-qr-code')
                ->hiddenLabel()
                ->schema([
                    TextEntry::make('placeholder')
                        ->label('Importeer EAN codes voor producten')
                        ->label('Gebruik een excel/csv bestand met 1 kolom met de EAN codes. Deze worden toegevoegd aan de producten zonder EAN code. Dit zijn er momenteel ' . Product::whereNull('ean')->count() . '. Maak een bestand met nooit meer dan he lege aantal EANs. De EAN codes dienen uniek te zijn.')
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

                    ImportEANCodes::dispatch($data['file']);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren wordt op de achtergrond uitgevoerd.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
