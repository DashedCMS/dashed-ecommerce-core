<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Classes\Guards\PriceGuard;
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
                ->label(__('Exporteer'))
                ->hiddenLabel()
                ->icon('heroicon-s-arrow-down-tray')
                ->action(function () {
                    Notification::make()
                        ->title(__('Exporteren'))
                        ->body(__('Het exporteren is gelukt.'))
                        ->success()
                        ->send();

                    return Excel::download(new ProductsToEdit(), 'Producten van ' . Customsetting::get('site_name') . '.xlsx');
                }),
            Action::make('import')
                ->label(__('Importeer'))
                ->icon('heroicon-s-arrow-up-tray')
                ->hiddenLabel()
                ->schema([
                    FileUpload::make('file')
                        ->label(__('Bestand'))
                        ->disk('local')
                        ->directory('imports')
                        ->rules([
                            'required',
                            'file',
                            'mimes:csv,xlsx',
                        ]),
                ])
                ->action(function ($data) {
                    // Prijsvangrail: een import met prijzen die flink afwijken of
                    // onder de inkoopprijs zakken, vraagt eerst om een bevestiging.
                    $analysis = rescue(
                        fn () => PriceGuard::analyzeImport(Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray { public function array(array $array): void {} }, Storage::disk('local')->path($data['file']))[0] ?? []),
                        ['count' => 0, 'rows' => []],
                    );

                    if ($analysis['count'] > 0) {
                        $this->priceImportAnalysis = $analysis;
                        $this->replaceMountedAction('confirmPriceImport', ['file' => $data['file']]);

                        return;
                    }

                    $this->dispatchProductImport($data['file']);
                }),
            ActionGroup::make([
                Action::make('processGs1File')
                    ->label(__('GS1-bestand verwerken'))
                    ->icon('heroicon-s-arrow-path')
                    // Niet Gs1RunResource::canCreate(): die staat bewust op false.
                    ->authorize(fn () => auth()->user()?->can('create', \Dashed\DashedEcommerceCore\Models\Gs1Run::class) ?? false)
                    ->modalHeading(__('GS1-bestand verwerken'))
                    ->modalDescription(__('Upload de download uit mijnGS1. Het CMS zoekt vrije codes, wijst ze toe aan producten zonder EAN en maakt een bestand om terug te uploaden. Er verandert pas iets als je op de volgende pagina op Toewijzen klikt.'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('GS1 bestand'))
                            ->disk('local')
                            ->directory('gs1-runs')
                            ->required()
                            // Alleen xlsx: de keuzelijsten worden uit de xlsx-XML gelezen.
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ]),
                    ])
                    ->action(function (array $data) {
                        try {
                            // Gs1RunStarter controleert het pad zelf: het komt uit de Livewire-staat.
                            $run = app(\Dashed\DashedEcommerceCore\Services\Gs1\Gs1RunStarter::class)
                                ->start(is_string($data['file'] ?? null) ? $data['file'] : '', auth()->id(), (string) Sites::getActive());
                        } catch (\Throwable $exception) {
                            if (! $exception instanceof \RuntimeException) {
                                report($exception);
                            }

                            Notification::make()
                                ->title(__('Bestand niet verwerkt'))
                                ->body($exception instanceof \RuntimeException
                                    ? $exception->getMessage()
                                    : __('Het bestand kon niet gelezen worden. Controleer of het de download uit mijnGS1 is.'))
                                ->danger()
                                ->persistent()
                                ->send();

                            return null;
                        }

                        return redirect(\Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource::getUrl('view', ['record' => $run]));
                    }),
                Action::make('gs1Runs')
                    ->label(__('Eerdere runs'))
                    ->icon('heroicon-o-clock')
                    ->url(fn () => \Dashed\DashedEcommerceCore\Filament\Resources\Gs1RunResource::getUrl('index')),
                Action::make('exportForGs1')
                    ->label(__('Exporteer voor GS1'))
                    ->icon('heroicon-s-arrow-down-tray')
                    ->requiresConfirmation()
                    ->modalHeading(__('Exporteer producten zonder EAN voor GS1'))
                    ->modalDescription(fn () => __('Er worden :aantal producten geëxporteerd. Standaardwaardes komen uit Instellingen → GS1, eventueel overschreven per categorie of product. Je kunt het bestand aanpassen vóór upload bij mijnGS1.', ['aantal' => cache()->remember('products_without_ean_count', 300, fn () => Product::query()->needsGs1Code()->count())]))
                    ->modalSubmitActionLabel(__('Download bestand'))
                    ->action(function () {
                        $siteId = (string) (Sites::getActive() ?: (Sites::getFirstSite()['id'] ?? ''));
                        $tmpPath = tempnam(sys_get_temp_dir(), 'gs1-export-') . '.xlsx';

                        $count = (new Gs1ExportBuilder(new Gs1FileWriter()))
                            ->buildForProductsWithoutEan($siteId, $tmpPath);

                        if ($count === 0) {
                            Notification::make()
                                ->title(__('Geen producten zonder EAN'))
                                ->warning()
                                ->send();

                            return null;
                        }

                        return response()->download($tmpPath, 'gs1-export-' . now()->format('Y-m-d-His') . '.xlsx')->deleteFileAfterSend();
                    }),
                Action::make('syncEanFromGs1')
                    ->label(__('Sync EAN uit GS1 bestand'))
                    ->icon('heroicon-s-arrow-up-tray')
                    ->modalHeading(__('Synchroniseer EAN-codes uit GS1 bestand'))
                    ->modalDescription(__('Upload het Excel-bestand dat je in mijnGS1 hebt gedownload. Per rij wordt op productnaam gematcht; alleen producten zonder EAN krijgen er één toegekend.'))
                    ->schema([
                        FileUpload::make('file')
                            ->label(__('GS1 bestand'))
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
                            ->title(__('GS1 sync klaar'))
                            ->body($body)
                            ->success(count($result->conflicts) === 0)
                            ->warning(count($result->conflicts) > 0)
                            ->persistent()
                            ->send();

                        cache()->forget('products_without_ean_count');
                    }),
            ])
                ->label(__('GS1'))
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->button(),
        ];
    }

    /** @var array{count: int, rows: array<int, array{id: int, name: string, old: ?float, new: float, reasons: array<int, string>}>} */
    #[\Livewire\Attributes\Locked]
    public array $priceImportAnalysis = ['count' => 0, 'rows' => []];

    public function confirmPriceImportAction(): Action
    {
        return Action::make('confirmPriceImport')
            ->modalHeading(__('Kloppen deze prijzen?'))
            ->modalDescription(fn () => new \Illuminate\Support\HtmlString(
                '<p>'.e(trans_choice(':aantal product krijgt een prijs die flink afwijkt of onder de inkoopprijs ligt.|:aantal producten krijgen een prijs die flink afwijkt of onder de inkoopprijs ligt.', $this->priceImportAnalysis['count'], ['aantal' => $this->priceImportAnalysis['count']])).'</p>'.
                '<table style="margin-top:.75rem;width:100%;font-size:.875rem;text-align:left"><thead><tr><th>'.e(__('Product')).'</th><th>'.e(__('Oud')).'</th><th>'.e(__('Nieuw')).'</th></tr></thead><tbody>'.
                collect(array_slice($this->priceImportAnalysis['rows'], 0, PriceGuard::PREVIEW_LIMIT))
                    ->map(fn ($row) => '<tr><td>'.e($row['name']).'<br><small>'.e(implode(' ', $row['reasons'])).'</small></td><td>'.e(PriceGuard::format($row['old'])).'</td><td>'.e(PriceGuard::format($row['new'])).'</td></tr>')
                    ->implode('').
                '</tbody></table>'
            ))
            ->modalSubmitActionLabel(__('Toch importeren'))
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (array $arguments): void {
                $this->priceImportAnalysis = ['count' => 0, 'rows' => []];
                $this->dispatchProductImport((string) ($arguments['file'] ?? ''));
            });
    }

    protected function dispatchProductImport(string $file): void
    {
        // Alleen een bestand dat de importactie zelf in imports/ heeft gezet.
        if ($file === '' || ! str_starts_with($file, 'imports/')) {
            return;
        }

        ImportProductToEditJob::dispatch($file);

        Notification::make()
            ->title(__('Importeren'))
            ->body(__('Het importeren wordt op de achtergrond uitgevoerd.'))
            ->success()
            ->send();
    }
}
