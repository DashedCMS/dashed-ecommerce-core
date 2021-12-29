<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages;

use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\EditRecord;
use Qubiqx\QcommerceCore\Classes\Locales;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilter;
use Qubiqx\QcommerceEcommerceCore\Classes\ProductCategories;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristic;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristics;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Bewerk product';

    public function mount($record): void
    {
        $thisRecord = $this->getRecord($record);
        foreach (Locales::getLocales() as $locale) {
            if (!$thisRecord->images) {
                $images = $thisRecord->getTranslation('images', $locale['id']);
                if (!$images) {
                    if (!is_array($images)) {
                        $thisRecord->setTranslation('images', $locale['id'], []);
                        $thisRecord->save();
                    }
                }
            }
        }
        parent::mount($record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        while (Product::where('id', '!=', $this->record->id)->where('slug->' . $this->activeFormLocale, $data['slug'])->count()) {
            $data['slug'] .= Str::random(1);
        }

        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        $content = $data['content'] ?? [];
        $data['content'] = $this->record->content;
        $data['content'][$this->activeFormLocale] = $content;

        $images = $data['images'] ?? [];
        $data['images'] = $this->record->images;
        $data['images'][$this->activeFormLocale] = $images;

        return $data;
    }

    public function afterFill(): void
    {
        $productFilters = ProductFilter::with(['productFilterOptions'])->get();

        if ($this->record->parentProduct) {
            $activeProductFilters = $this->record->parentProduct->activeProductFilters;
        } else {
            $activeProductFilters = $this->record->activeProductFilters;
        }

        foreach ($productFilters as $productFilter) {
            $this->data["product_filter_$productFilter->id"] = (bool)$activeProductFilters->contains($productFilter->id);
            $this->data["product_filter_{$productFilter->id}_use_for_variations"] = $activeProductFilters->contains($productFilter->id) ? ($this->record->activeProductFilters()->wherePivot('product_filter_id', $productFilter->id)->first()->pivot->use_for_variations ?? 0) : 0;

            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                $this->data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"] = $this->record->productFilters()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $productFilterOption->id)->exists();
            }
        }

        $productCharacteristics = ProductCharacteristics::get();

        foreach ($productCharacteristics as $productCharacteristic) {
            $this->data["product_characteristic_$productCharacteristic->id"] = $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->exists() ? $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first()->getTranslation('value', $this->activeFormLocale) : null;
        }

        foreach ($this->data['productExtras'] as &$productExtra) {
            $productExtra['name'] = $productExtra['name'][$this->activeFormLocale] ?? '';
            foreach ($productExtra['productExtraOptions'] as &$productExtraOption) {
                $productExtraOption['value'] = $productExtraOption['value'][$this->activeFormLocale] ?? '';
            }
        }
    }

    public function afterSave(): void
    {
        foreach ($this->record->childProducts as $childProduct) {
            $childProduct->site_ids = $this->record->site_ids;
            $childProduct->save();
        }

        $selectedProductCategories = ProductCategories::getFromIdsWithParents($this->record->productCategories()->pluck('product_category_id'));
        if ($this->record->parentProduct) {
            foreach ($this->record->parentProduct->childProducts as $childProduct) {
                $childProduct->productCategories()->sync($selectedProductCategories);
            }
        } else {
            $this->record->productCategories()->sync($selectedProductCategories);
        }

        if ($this->record->parentProduct) {
            foreach ($this->record->parentProduct->childProducts as $childProduct) {
                $childProduct->shippingClasses()->sync($this->record->shippingClasses);
            }
        }

        $productFilters = ProductFilter::with(['productFilterOptions'])->get();

        if (($this->record->type == 'variable' && !$this->record->parent_product_id) || $this->record->type == 'simple') {
            $this->record->activeProductFilters()->detach();
            foreach ($productFilters as $productFilter) {
                if ($this->data["product_filter_$productFilter->id"]) {
                    $this->record->activeProductFilters()->attach($productFilter->id);
                    $this->record->activeProductFilters()->updateExistingPivot($productFilter->id, [
                        'use_for_variations' => $this->data["product_filter_{$productFilter->id}_use_for_variations"],
                    ]);
                }
            }
        }

        if (($this->record->type == 'variable' && $this->record->parent_product_id) || $this->record->type == 'simple') {
            $this->record->productFilters()->detach();
            foreach ($productFilters as $productFilter) {
                if ($this->data["product_filter_$productFilter->id"] && ($this->record->activeProductFilters->contains($productFilter->id) || ($this->record->parentProduct && $this->record->parentProduct->activeProductFilters->contains($productFilter->id)))) {
                    foreach ($productFilter->productFilterOptions as $productFilterOption) {
                        if ($this->data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"]) {
                            $this->record->productFilters()->attach($productFilter->id, ['product_filter_option_id' => $productFilterOption->id]);
                        }
                    }
                }
            }
        }

        $productCharacteristics = ProductCharacteristics::get();

        foreach ($productCharacteristics as $productCharacteristic) {
            $thisProductCharacteristic = ProductCharacteristic::where('product_id', $this->record->id)->where('product_characteristic_id', $productCharacteristic->id)->first();
            if (!$thisProductCharacteristic) {
                $thisProductCharacteristic = new ProductCharacteristic();
                $thisProductCharacteristic->product_id = $this->record->id;
                $thisProductCharacteristic->product_characteristic_id = $productCharacteristic->id;
            }
            $thisProductCharacteristic->setTranslation('value', $this->activeFormLocale, $this->data["product_characteristic_$productCharacteristic->id"]);
            $thisProductCharacteristic->save();
        }
    }

    protected function getBreadcrumbs(): array
    {
        if (!$this->record->parentProduct) {
            return parent::getBreadcrumbs();
        }

        $breadcrumbs = parent::getBreadcrumbs();
        $breadcrumbs = array_merge([route('filament.resources.products.edit', [$this->record->parentProduct->id]) => "{$this->record->parentProduct->name}"], $breadcrumbs);
        $breadcrumbs = array_merge([route('filament.resources.products.index') => "Producten"], $breadcrumbs);

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        return array_merge(parent::getActions() ?: [], [
            ButtonAction::make('Bekijk product')
                ->url($this->record->getUrl())
                ->openUrlInNewTab(),
            $this->getActiveFormLocaleSelectAction(),
        ]);
    }

//    public function generateRandomCode(): void
//    {
//        $this->data['code'] = Str::upper(Str::random(10));
//    }
}
