<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedCore\Filament\Concerns\HasCreatableCMSActions;
use Dashed\DashedEcommerceCore\Jobs\CreateMissingProductVariationsJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;

class CreateProductGroup extends CreateRecord
{
    use HasCreatableCMSActions;

    protected static string $resource = ProductGroupResource::class;

    public array $activeProductFilters = [];
    public array $enabledProductFilters = [];

    protected function getActions(): array
    {
        return self::CMSActions();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? (isset($data['parent_id']) && $data['parent_id'] ? Product::find($data['parent_id'])->site_ids : [Sites::getFirstSite()['id']]);

        $productFilters = ProductFilter::with(['productFilterOptions'])->get();

        $activeProductFilters = [];
        foreach ($productFilters as $productFilter) {
            if (in_array($productFilter->id, $data['productFilters'])) {
                $activeProductFilters[] = [
                    'id' => $productFilter->id,
                    'attributes' => [
                        'use_for_variations' => $data["product_filter_{$productFilter->id}_use_for_variations"],
                    ],
                ];
            }
        }
        $this->activeProductFilters = $activeProductFilters;

        $enabledProductFilters = [];
        foreach ($productFilters as $productFilter) {
            foreach ($data['product_filter_options_' . $productFilter->id] ?? [] as $optionId) {
                $enabledProductFilters[] = [
                    'id' => $productFilter->id,
                    'attributes' => [
                        'product_filter_option_id' => $optionId,
                    ],
                ];
            }
        }
        $this->enabledProductFilters = $enabledProductFilters;

        unset($data['productFilters']);
        foreach ($productFilters as $productFilter) {
            unset($data['product_filter_options_' . $productFilter->id]);
            unset($data["product_filter_{$productFilter->id}_use_for_variations"]);
        }

        return $data;
    }

    public function afterCreate()
    {
        foreach ($this->activeProductFilters as $productFilter) {
            $this->record->activeProductFilters()->attach($productFilter['id'], $productFilter['attributes']);
        }

        foreach ($this->enabledProductFilters as $productFilter) {
            $this->record->enabledProductFilterOptions()->attach($productFilter['id'], $productFilter['attributes']);
        }

        CreateMissingProductVariationsJob::dispatch($this->record);
    }
}
