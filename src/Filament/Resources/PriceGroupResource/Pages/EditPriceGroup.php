<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource\Pages;

use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Dashed\DashedEcommerceCore\Jobs\ProcessPricesPerPriceGroup;
use Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource;

class EditPriceGroup extends EditRecord
{
    protected static string $resource = PriceGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $productCategories = ProductCategory::all();

        $data['product_category_ids'] = DB::table('dashed__product_category_price_group')
            ->where('price_group_id', $this->record->id)
            ->pluck('product_category_id')
            ->toArray();

        foreach ($productCategories as $productCategory) {
            if (in_array($productCategory->id, $data['product_category_ids'])) {
                $row = DB::table('dashed__product_category_price_group')
                    ->where('price_group_id', $this->record->id)
                    ->where('product_category_id', $productCategory->id)
                    ->first();
                $data[$productCategory->id . '_category_discount_price'] = $row->discount_price ?? null;
                $data[$productCategory->id . '_category_discount_percentage'] = $row->discount_percentage ?? null;
            }
        }

        $extraOptionRows = DB::table('dashed__product_extra_option_price_group')
            ->where('price_group_id', $this->record->id)
            ->get();

        foreach ($extraOptionRows as $row) {
            $data['extra_option_' . $row->product_extra_option_id . '_price'] = $row->price ?? null;
            $data['extra_option_' . $row->product_extra_option_id . '_discount_percentage'] = $row->discount_percentage ?? null;
        }

        return parent::mutateFormDataBeforeFill($data);
    }

    protected function afterSave(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if (preg_match('/^extra_option_(\d+)_price$/', $key, $m)) {
                $optionId = (int) $m[1];
                $price = $value;
                $percentage = $data['extra_option_' . $optionId . '_discount_percentage'] ?? null;

                if ($price === null && $percentage === null) {
                    DB::table('dashed__product_extra_option_price_group')
                        ->where('price_group_id', $this->record->id)
                        ->where('product_extra_option_id', $optionId)
                        ->delete();

                    continue;
                }

                DB::table('dashed__product_extra_option_price_group')->updateOrInsert(
                    ['price_group_id' => $this->record->id, 'product_extra_option_id' => $optionId],
                    ['price' => $price, 'discount_percentage' => $percentage]
                );
            }
        }

        ProcessPricesPerPriceGroup::dispatch($this->record->id, $data);
    }
}
