<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource\Pages;

use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\FileUpload;
use Illuminate\Contracts\Support\Htmlable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Jobs\ProcessPricesPerUser;
use Dashed\DashedEcommerceCore\Jobs\ImportPricesPerUserPerProduct;
use Dashed\DashedEcommerceCore\Exports\PricePerProductForUserExport;
use Dashed\DashedEcommerceCore\Exports\PricePerCategoryForUserExport;
use Dashed\DashedEcommerceCore\Filament\Resources\PricePerUserResource;

class EditPricePerUser extends EditRecord
{
    protected static string $resource = PricePerUserResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Bewerk prijzen voor  ' . $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportProducts')
                ->label('Producten')
                ->icon('heroicon-s-arrow-down-tray')
                ->action(function () {
                    Notification::make()
                        ->title('Exporteren')
                        ->body('Het exporteren is gelukt.')
                        ->success()
                        ->send();

                    return Excel::download(new PricePerProductForUserExport($this->record), 'Prijzen voor ' . $this->record->name . '.xlsx');
                }),
            Action::make('importProducts')
                ->label('Producten')
                ->icon('heroicon-s-arrow-up-tray')
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

                    ImportPricesPerUserPerProduct::dispatch($this->record, $data['file']);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren is gelukt, refresh de pagina.')
                        ->success()
                        ->send();
                }),
            Action::make('export')
                ->label('Categorieen')
                ->icon('heroicon-s-arrow-down-tray')
                ->action(function () {
                    Notification::make()
                        ->title('Exporteren')
                        ->body('Het exporteren is gelukt.')
                        ->success()
                        ->send();

                    return Excel::download(new PricePerCategoryForUserExport($this->record), 'Prijzen van categorieen voor ' . $this->record->name . '.xlsx');
                }),
            Action::make('import')
                ->label('Categorieen')
                ->icon('heroicon-s-arrow-up-tray')
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

                    ImportPricesPerUserPerProduct::dispatch($this->record, $data['file']);

                    Notification::make()
                        ->title('Importeren')
                        ->body('Het importeren is gelukt, refresh de pagina.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $products = Product::all();
        $productCategories = ProductCategory::all();

        //        $data['product_ids'] = DB::table('dashed__product_user')
        //            ->where('user_id', $this->record->id)
        //            ->pluck('product_id')
        //            ->toArray();

        $data['product_category_ids'] = DB::table('dashed__product_category_user')
            ->where('user_id', $this->record->id)
            ->pluck('product_category_id')
            ->toArray();

        //        foreach ($products as $product) {
        //            if (in_array($product->id, $data['product_ids'])) {
        //                $productUser = DB::table('dashed__product_user')
        //                    ->where('user_id', $this->record->id)
        //                    ->where('product_id', $product->id)
        //                    ->first();
        //                $data[$product->id . '_price'] = $productUser->price ?? null;
        //                $data[$product->id . '_discount_price'] = $productUser->discount_price ?? null;
        //                $data[$product->id . '_discount_percentage'] = $productUser->discount_percentage ?? null;
        //            }
        //        }

        foreach ($productCategories as $productCategory) {
            if (in_array($productCategory->id, $data['product_category_ids'])) {
                $productCategoryUser = DB::table('dashed__product_category_user')
                    ->where('user_id', $this->record->id)
                    ->where('product_category_id', $productCategory->id)
                    ->first();
                $data[$productCategory->id . '_category_discount_price'] = $productCategoryUser->discount_price ?? null;
                $data[$productCategory->id . '_category_discount_percentage'] = $productCategoryUser->discount_percentage ?? null;
            }
        }

        return parent::mutateFormDataBeforeFill($data); // TODO: Change the autogenerated stub
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        ProcessPricesPerUser::dispatch($this->record, $data)->onQueue('ecommerce');

        $data = [];

        return parent::mutateFormDataBeforeSave($data); // TODO: Change the autogenerated stub
    }
}
