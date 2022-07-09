<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource\Pages;

use Filament\Pages\Actions\Action;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Pages\Actions\ButtonAction;
use Filament\Resources\Pages\EditRecord;
use Qubiqx\QcommerceCore\Classes\Locales;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtra;
use Qubiqx\QcommerceEcommerceCore\Models\ProductFilter;
use Qubiqx\QcommerceEcommerceCore\Classes\ProductCategories;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristic;
use Qubiqx\QcommerceEcommerceCore\Models\ProductCharacteristics;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;
//    protected static string $view = 'qcommerce-ecommerce-core::products.edit-product';

    protected static ?string $title = 'Bewerk product';

    public function mount($record): void
    {
        $thisRecord = $this->resolveRecord($record);
        foreach (Locales::getLocales() as $locale) {
            if (! $thisRecord->images) {
                $images = $thisRecord->getTranslation('images', $locale['id']);
                if (! $images) {
                    if (! is_array($images)) {
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

        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        $content = $data['content'] ?? [];
        $data['content'] = $this->record->content ?: [];
        $data['content'][$this->activeFormLocale] = $content;

        $images = $data['images'] ?? [];
        $data['images'] = $this->record->images;
        $data['images'][$this->activeFormLocale] = $images;


        $validProductExtraIds = [];

        foreach ($data['productExtras'] as $productExtraKey => $productExtra) {
            if (isset($productExtra['productExtraId']) && $productExtra['productExtraId']) {
                $newProductExtra = ProductExtra::find($productExtra['productExtraId']);
            } else {
                $newProductExtra = new ProductExtra();
            }
            $newProductExtra->product_id = $this->record->id;
            $newProductExtra->type = $productExtra['type'];
            $newProductExtra->required = $productExtra['required'];
            $newProductExtra->setTranslation('name', $this->activeFormLocale, $productExtra['name']);
            $newProductExtra->save();
            $validProductExtraIds[] = $newProductExtra->id;
            $data['productExtras'][$productExtraKey]['productExtraId'] = $newProductExtra->id;

            $validProductExtraOptionIds = [];

            foreach ($productExtra['productExtraOptions'] as $productExtraOptionKey => $productExtraOption) {
                if (isset($productExtraOption['productExtraOptionId']) && $productExtraOption['productExtraOptionId']) {
                    $newProductExtraOption = ProductExtraOption::find($productExtraOption['productExtraOptionId']);
                } else {
                    $newProductExtraOption = new ProductExtraOption();
                }
                $newProductExtraOption->product_extra_id = $newProductExtra->id;
                $newProductExtraOption->setTranslation('value', $this->activeFormLocale, $productExtraOption['value']);
                $newProductExtraOption->price = $productExtraOption['price'];
                $newProductExtraOption->calculate_only_1_quantity = $productExtraOption['calculate_only_1_quantity'];
                $newProductExtraOption->save();
                $data['productExtras'][$productExtraKey]['productExtraOptions'][$productExtraOptionKey]['productExtraOptionId'] = $newProductExtraOption->id;
                $validProductExtraOptionIds[] = $newProductExtraOption->id;
            }

            $newProductExtra->productExtraOptions()->whereNotIn('id', $validProductExtraOptionIds)->forceDelete();
        }

        foreach ($this->record->productExtras()->whereNotIn('id', $validProductExtraIds)->get() as $productExtra) {
            $productExtra->productExtraOptions()->forceDelete();
            $productExtra->forceDelete();
        }

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

        foreach ($this->record->productExtras as $productExtra) {
            $productExtraOptions = [];
            foreach ($productExtra->productExtraOptions as $productExtraOption) {
                $productExtraOptions[] = [
                    'value' => $productExtraOption->getTranslation('value', $this->activeFormLocale),
                    'price' => $productExtraOption->price,
                    'calculate_only_1_quantity' => $productExtraOption->calculate_only_1_quantity,
                    'productExtraOptionId' => $productExtraOption->id,
                ];
            }

            $this->data['productExtras'][] = [
                'name' => $productExtra->getTranslation('name', $this->activeFormLocale),
                'type' => $productExtra->type,
                'required' => $productExtra->required,
                'productExtraId' => $productExtra->id,
                'productExtraOptions' => $productExtraOptions,
            ];
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

        if (($this->record->type == 'variable' && ! $this->record->parent_product_id) || $this->record->type == 'simple') {
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
            if (! $thisProductCharacteristic) {
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
        if (! $this->record->parentProduct) {
            return parent::getBreadcrumbs();
        }

        $breadcrumbs = parent::getBreadcrumbs();
        $breadcrumbs = array_merge([route('filament.resources.products.edit', [$this->record->parentProduct->id]) => "{$this->record->parentProduct->name}"], $breadcrumbs);
        $breadcrumbs = array_merge([route('filament.resources.products.index') => "Producten"], $breadcrumbs);

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        $buttons = [];

        if ($this->record->type != 'variable' || $this->record->parent_product_id) {
            $buttons[] = Action::make('Bekijk product')
                ->url($this->record->getUrl())
                ->openUrlInNewTab();
            $buttons[] = Action::make('Dupliceer product')
                ->action('duplicateProduct')
                ->color('warning');
        }

        $buttons[] = $this->getActiveFormLocaleSelectAction();

        return array_merge(parent::getActions() ?: [], $buttons);
    }

    public function duplicateProduct()
    {
        $newProduct = $this->record->replicate();
        $newProduct->sku = 'SKU' . rand(10000, 99999);
        foreach (Locales::getLocales() as $locale) {
            $newProduct->setTranslation('slug', $locale['id'], $newProduct->getTranslation('slug', $locale['id']));
            while (Product::where('slug->' . $locale['id'], $newProduct->getTranslation('slug', $locale['id']))->count()) {
                $newProduct->setTranslation('slug', $locale['id'], $newProduct->getTranslation('slug', $locale['id']) . Str::random(1));
            }
        }
        $newProduct->save();

        $this->record->load('productCategories', 'shippingClasses', 'productFilters', 'activeProductFilters', 'productCharacteristics', 'productExtras');

        $newProduct->productCategories()->sync($this->record->productCategories);
        $newProduct->shippingClasses()->sync($this->record->shippingClasses);
        $newProduct->activeProductFilters()->sync($this->record->activeProductFilters);

        foreach (DB::table('qcommerce__product_characteristic')->where('product_id', $this->record->id)->whereNull('deleted_at')->get() as $productCharacteristic) {
            DB::table('qcommerce__product_characteristic')->insert([
                'product_id' => $newProduct->id,
                'product_characteristic_id' => $productCharacteristic->product_characteristic_id,
                'value' => $productCharacteristic->value,
            ]);
        }

        foreach (DB::table('qcommerce__product_filter')->where('product_id', $this->record->id)->get() as $productFilter) {
            DB::table('qcommerce__product_filter')->insert([
                'product_id' => $newProduct->id,
                'product_filter_id' => $productFilter->product_filter_id,
                'product_filter_option_id' => $productFilter->product_filter_option_id,
            ]);
        }

        foreach (DB::table('qcommerce__product_suggested_product')->where('product_id', $this->record->id)->get() as $suggestedProduct) {
            DB::table('qcommerce__product_suggested_product')->insert([
                'product_id' => $newProduct->id,
                'suggested_product_id' => $suggestedProduct->suggested_product_id,
                'order' => $suggestedProduct->order,
            ]);
        }

        foreach (DB::table('qcommerce__product_extras')->where('product_id', $this->record->id)->whereNull('deleted_at')->get() as $productExtra) {
            $newProductExtra = new ProductExtra();
            $newProductExtra->product_id = $newProduct->id;
            foreach (json_decode($productExtra->name, true) as $locale => $name) {
                $newProductExtra->setTranslation('name', $locale, $name);
            }
            $newProductExtra->type = $productExtra->type;
            $newProductExtra->required = $productExtra->required;
            $newProductExtra->save();

            foreach (DB::table('qcommerce__product_extra_options')->where('product_extra_id', $productExtra->id)->whereNull('deleted_at')->get() as $productExtraOption) {
                DB::table('qcommerce__product_extra_options')->insert([
                    'product_extra_id' => $newProductExtra->id,
                    'value' => $productExtraOption->value,
                    'price' => $productExtraOption->price,
                ]);
            }
        }

        return redirect(route('filament.resources.products.edit', [$newProduct]));
    }
}
