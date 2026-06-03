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
use STS\FilamentImpersonate\Actions\Impersonate;
use Dashed\DashedEcommerceCore\Models\PriceGroup;
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
        return 'Bewerk prijzen voor  '.$this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Impersonate::make(),
            Action::make('exportProducts')
                ->label('Producten')
                ->icon('heroicon-s-arrow-down-tray')
                ->action(function () {
                    Notification::make()
                        ->title('Exporteren')
                        ->body('Het exporteren is gelukt.')
                        ->success()
                        ->send();

                    return Excel::download(new PricePerProductForUserExport($this->record), 'Prijzen voor '.$this->record->name.'.xlsx');
                }),
            Action::make('importProducts')
                ->label('Producten')
                ->icon('heroicon-s-arrow-up-tray')
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

                    return Excel::download(new PricePerCategoryForUserExport($this->record), 'Prijzen van categorieen voor '.$this->record->name.'.xlsx');
                }),
            Action::make('import')
                ->label('Categorieen')
                ->icon('heroicon-s-arrow-up-tray')
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
                $data[$productCategory->id.'_category_discount_price'] = $productCategoryUser->discount_price ?? null;
                $data[$productCategory->id.'_category_discount_percentage'] = $productCategoryUser->discount_percentage ?? null;
            }
        }

        $extraRows = DB::table('dashed__product_extra_option_user')
            ->where('user_id', $this->record->id)->get();
        foreach ($extraRows as $row) {
            $data['extra_option_' . $row->product_extra_option_id . '_user_price'] = $row->price;
            $data['extra_option_' . $row->product_extra_option_id . '_user_discount_percentage'] = $row->discount_percentage;
        }

        $parentExtraRows = DB::table('dashed__product_extra_user')
            ->where('user_id', $this->record->id)->get();
        foreach ($parentExtraRows as $row) {
            $data['extra_' . $row->product_extra_id . '_user_price'] = $row->price;
            $data['extra_' . $row->product_extra_id . '_user_discount_percentage'] = $row->discount_percentage;
        }

        return parent::mutateFormDataBeforeFill($data); // TODO: Change the autogenerated stub
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $priceGroupId = $data['price_group_id'] ?? null;
        $group = $priceGroupId ? PriceGroup::find($priceGroupId) : null;

        // Bij een gekozen prijsgroep staat custom pricing altijd aan en wordt
        // de ex BTW-instelling van die groep overgenomen (die twee toggles zijn
        // dan verborgen in het formulier).
        $pricing = PricePerUserResource::resolveUserPricing($group, $data);
        $this->record->has_custom_pricing = $pricing['has_custom_pricing'];
        $this->record->show_prices_ex_vat = $pricing['show_prices_ex_vat'];
        $this->record->save();

        ProcessPricesPerUser::dispatch($this->record, $data)->onQueue('ecommerce');

        // Preserve price_group_id so Filament writes it to the users table.
        // Keep the key even when null, otherwise detaching a user from a
        // group (clearing the select) would never be persisted.
        $data = ['price_group_id' => $priceGroupId];

        return parent::mutateFormDataBeforeSave($data);
    }

    protected function afterSave(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (preg_match('/^extra_option_(\d+)_user_price$/', $key, $m)) {
                $optionId = (int) $m[1];
                $price = $value;
                $percentage = $data['extra_option_' . $optionId . '_user_discount_percentage'] ?? null;

                if ($price === null && $percentage === null) {
                    DB::table('dashed__product_extra_option_user')
                        ->where('user_id', $this->record->id)
                        ->where('product_extra_option_id', $optionId)
                        ->delete();

                    continue;
                }

                DB::table('dashed__product_extra_option_user')->updateOrInsert(
                    ['user_id' => $this->record->id, 'product_extra_option_id' => $optionId],
                    ['price' => $price, 'discount_percentage' => $percentage]
                );
            }

            if (preg_match('/^extra_(\d+)_user_price$/', $key, $m)) {
                $extraId = (int) $m[1];
                $price = $value;
                $percentage = $data['extra_' . $extraId . '_user_discount_percentage'] ?? null;

                if ($price === null && $percentage === null) {
                    DB::table('dashed__product_extra_user')
                        ->where('user_id', $this->record->id)
                        ->where('product_extra_id', $extraId)
                        ->delete();

                    continue;
                }

                DB::table('dashed__product_extra_user')->updateOrInsert(
                    ['user_id' => $this->record->id, 'product_extra_id' => $extraId],
                    ['price' => $price, 'discount_percentage' => $percentage]
                );
            }
        }
    }
}
