<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
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
use Dashed\DashedEcommerceCore\Jobs\BulkUpdateProductPrices;
use Dashed\DashedEcommerceCore\Filament\Imports\EANCodesImporter;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Widgets\ProductOutOfStockStat;

class ListProducts extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected Width|string|null $maxContentWidth = 'full';

    protected function getTableQuery(): ?Builder
    {
        return Product::query()->with(['productGroup']);
    }

    protected function getHeaderWidgets(): array
    {
        return array_merge(parent::getHeaderWidgets() ?? [], [
            ProductOutOfStockStat::class,
        ]);
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
            Action::make('bulkUpdatePrices')
                ->label('Prijzen aanpassen')
                ->icon('heroicon-s-banknotes')
                ->color('warning')
                ->modalHeading('Alle product-prijzen verhogen of verlagen')
                ->modalDescription('Past de prijs van alle producten aan met een vast euro-bedrag of percentage. Gebruik een negatief getal om te verlagen. De update draait op de achtergrond.')
                ->modalSubmitActionLabel('Doorvoeren')
                ->schema([
                    Radio::make('mode')
                        ->label('Soort aanpassing')
                        ->options([
                            'euro' => 'Vast bedrag in euro',
                            'percent' => 'Percentage',
                        ])
                        ->default('percent')
                        ->required()
                        ->inline()
                        ->reactive(),
                    TextInput::make('amount')
                        ->label(fn (callable $get) => $get('mode') === 'euro' ? 'Bedrag in euro (negatief = verlagen)' : 'Percentage (negatief = verlagen)')
                        ->numeric()
                        ->step(0.01)
                        ->required()
                        ->helperText(fn (callable $get) => $get('mode') === 'euro'
                            ? 'Voorbeeld: 1.50 telt €1,50 op bij elke prijs, -0.50 trekt €0,50 af.'
                            : 'Voorbeeld: 10 verhoogt met 10%, -5 verlaagt met 5%.'),
                    Toggle::make('include_discount_price')
                        ->label('Pas ook toe op aanbiedingsprijs (new_price)')
                        ->helperText('Aan: ook de "nieuwe prijs" (discount) wordt mee gewijzigd. Uit: alleen de standaardprijs wordt gewijzigd.')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $mode = $data['mode'] ?? 'percent';
                    $amount = (float) ($data['amount'] ?? 0);
                    $includeDiscount = (bool) ($data['include_discount_price'] ?? false);

                    if ($amount === 0.0) {
                        Notification::make()
                            ->title('Geen wijziging')
                            ->body('Vul een bedrag of percentage anders dan 0 in.')
                            ->warning()
                            ->send();

                        return;
                    }

                    BulkUpdateProductPrices::dispatch($mode, $amount, $includeDiscount);

                    Notification::make()
                        ->title('Prijswijziging gestart')
                        ->body('De update draait op de achtergrond. Vernieuw deze pagina over enkele minuten om de nieuwe prijzen te zien.')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(false),

            Action::make('importEANCodes')
                ->label('Importeer EAN codes')
                ->icon('heroicon-s-qr-code')
                ->hiddenLabel()
                ->schema([
                    TextEntry::make('placeholder')
                        ->label('Importeer EAN codes voor producten')
                        ->label(fn () => 'Gebruik een excel/csv bestand met 1 kolom met de EAN codes. Deze worden toegevoegd aan de producten zonder EAN code. Dit zijn er momenteel ' . cache()->remember('products_without_ean_count', 300, fn () => Product::whereNull('ean')->count()) . '. Maak een bestand met nooit meer dan he lege aantal EANs. De EAN codes dienen uniek te zijn.')
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
