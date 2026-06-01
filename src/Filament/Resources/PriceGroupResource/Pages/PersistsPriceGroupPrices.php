<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource\Pages;

use Illuminate\Support\Facades\DB;
use Dashed\DashedEcommerceCore\Jobs\ProcessPricesPerPriceGroup;

trait PersistsPriceGroupPrices
{
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
        }

        ProcessPricesPerPriceGroup::dispatch($this->record->id, $data);
    }
}
