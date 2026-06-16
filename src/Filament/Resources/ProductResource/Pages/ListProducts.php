<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Dashed\DashedCore\Classes\Sites;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Exports\ProductsToEdit;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1EanSyncer;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1FileReader;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1FileWriter;
use Dashed\DashedEcommerceCore\Jobs\ImportProductToEditJob;
use Dashed\DashedEcommerceCore\Services\Gs1\Gs1ExportBuilder;
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
            ActionGroup::make([
                Action::make('exportForGs1')
                    ->label('Exporteer voor GS1')
                    ->icon('heroicon-s-arrow-down-tray')
                    ->requiresConfirmation()
                    ->modalHeading('Exporteer producten zonder EAN voor GS1')
                    ->modalDescription(fn () => 'Er worden ' . cache()->remember('products_without_ean_count', 300, fn () => Product::withoutEan()->where('public', true)->where('is_bundle', false)->count()) . ' producten geëxporteerd. Standaardwaardes komen uit Instellingen → GS1, eventueel overschreven per categorie of product. Je kunt het bestand aanpassen vóór upload bij mijnGS1.')
                    ->modalSubmitActionLabel('Download bestand')
                    ->action(function () {
                        $siteId = Sites::getActive() ?: (Sites::getFirstSite()['id'] ?? 1);
                        $tmpPath = tempnam(sys_get_temp_dir(), 'gs1-export-') . '.xlsx';

                        $count = (new Gs1ExportBuilder(new Gs1FileWriter()))
                            ->buildForProductsWithoutEan((int) $siteId, $tmpPath);

                        if ($count === 0) {
                            Notification::make()
                                ->title('Geen producten zonder EAN')
                                ->warning()
                                ->send();

                            return null;
                        }

                        return response()->download($tmpPath, 'gs1-export-' . now()->format('Y-m-d-His') . '.xlsx')->deleteFileAfterSend();
                    }),
                Action::make('syncEanFromGs1')
                    ->label('Sync EAN uit GS1 bestand')
                    ->icon('heroicon-s-arrow-up-tray')
                    ->modalHeading('Synchroniseer EAN-codes uit GS1 bestand')
                    ->modalDescription('Upload het Excel-bestand dat je in mijnGS1 hebt gedownload. Per rij wordt op productnaam gematcht; alleen producten zonder EAN krijgen er één toegekend.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('GS1 bestand')
                            ->disk('local')
                            ->directory('gs1-sync')
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ]),
                    ])
                    ->action(function (array $data) {
                        $absolute = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);

                        $result = (new Gs1EanSyncer(new Gs1FileReader()))->sync($absolute);

                        $body = sprintf(
                            "%d EANs toegekend\n%d al gesynced\n%d overgeslagen (had al EAN)\n%d niet gevonden\n%d conflicten",
                            count($result->updated),
                            $result->alreadyInSync,
                            count($result->skippedHasEan),
                            count($result->notFound),
                            count($result->conflicts),
                        );

                        Notification::make()
                            ->title('GS1 sync klaar')
                            ->body($body)
                            ->success(count($result->conflicts) === 0)
                            ->warning(count($result->conflicts) > 0)
                            ->persistent()
                            ->send();

                        cache()->forget('products_without_ean_count');
                    }),
            ])
                ->label('GS1')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->button(),
        ];
    }
}
