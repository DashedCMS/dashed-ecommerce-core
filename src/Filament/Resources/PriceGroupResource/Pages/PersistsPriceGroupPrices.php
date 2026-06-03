<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource\Pages;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Models\PriceGroup;
use Dashed\DashedEcommerceCore\Jobs\ProcessPricesPerPriceGroup;

trait PersistsPriceGroupPrices
{
    /**
     * Beperk de formulierdata tot de echte kolommen van price_groups. De
     * vorm bevat dynamische, niet-kolom velden (product_category_ids,
     * extra_option_*_*, *_category_discount_*) die via pivot-tabellen worden
     * opgeslagen. Omdat models globaal ge-unguard zijn (DashedCoreServiceProvider)
     * negeert Eloquent $fillable en zou create()/update() deze velden als
     * kolommen proberen weg te schrijven. Dit strippen voorkomt de
     * "Unknown column"-fout; de pivot-velden blijven beschikbaar via
     * $this->form->getState() in afterCreate()/afterSave().
     */
    protected function onlyPriceGroupColumns(array $data): array
    {
        return Arr::only($data, (new PriceGroup())->getFillable());
    }

    /**
     * Persist the per-extra-option prices entered on the form and dispatch
     * the category-discount cascade. Shared by the create and edit pages so
     * both screens save the dynamic (non-column) form fields identically.
     */
    protected function persistPriceGroupPrices(array $data): void
    {
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

            if (preg_match('/^extra_(\d+)_price$/', $key, $m)) {
                $extraId = (int) $m[1];
                $price = $value;
                $percentage = $data['extra_' . $extraId . '_discount_percentage'] ?? null;

                if ($price === null && $percentage === null) {
                    DB::table('dashed__product_extra_price_group')
                        ->where('price_group_id', $this->record->id)
                        ->where('product_extra_id', $extraId)
                        ->delete();

                    continue;
                }

                DB::table('dashed__product_extra_price_group')->updateOrInsert(
                    ['price_group_id' => $this->record->id, 'product_extra_id' => $extraId],
                    ['price' => $price, 'discount_percentage' => $percentage]
                );
            }
        }

        ProcessPricesPerPriceGroup::dispatch($this->record->id, $data);
    }
}
