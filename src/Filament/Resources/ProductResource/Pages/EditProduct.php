<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedCore\Models\Redirect;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristic;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductResource::class;

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

        while (Product::where('id', '!=', $this->record->id)->where('slug->' . $this->activeLocale, $data['slug'])->count()) {
            $data['slug'] .= Str::random(1);
        }

        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        Redirect::handleSlugChange($this->record->getTranslation('slug', $this->activeLocale), $data['slug']);

        foreach ($this->record->childProducts as $childProduct) {
            $childProduct->site_ids = $data['site_ids'];
            $childProduct->save();
        }

        $selectedProductCategories = ProductCategories::getFromIdsWithParents($this->record->productCategories()->pluck('product_category_id'));
        if ($this->record->parent) {
            foreach ($this->record->parent->childProducts as $childProduct) {
                $childProduct->productCategories()->sync($selectedProductCategories);
            }
        } else {
            $this->record->productCategories()->sync($selectedProductCategories);
        }

        if ($data['parent_id'] ?? false) {
            foreach (Product::find($data['parent_id'])->childProducts as $childProduct) {
                $childProduct->shippingClasses()->sync($this->record->shippingClasses);
            }
        }

        $productFilters = ProductFilter::with(['productFilterOptions'])->get();

        //        Only if is simple or variable && parent
        if ((($data['type'] ?? 'variable') == 'variable' && ! ($data['parent_id'] ?? false)) || ($data['type'] ?? 'variable') == 'simple') {
            $this->record->activeProductFilters()->detach();
            foreach ($productFilters as $productFilter) {
                if ($data["product_filter_$productFilter->id"] ?? false) {
                    $id = $this->record->activeProductFilters()->attach($productFilter->id, [
                        'use_for_variations' => $data["product_filter_{$productFilter->id}_use_for_variations"],
                    ]);
                }
            }
        }

        if ((($data['type'] ?? 'variable') == 'variable' && ($data['parent_id'] ?? true)) || ($data['type'] ?? 'variable') == 'simple') {
            $this->record->productFilters()->detach();
            $this->record->enabledProductFilterOptions()->detach();
            foreach ($productFilters as $productFilter) {
                if ((($data["product_filter_$productFilter->id"] ?? false) && ($this->record->activeProductFilters->contains($productFilter->id)) || (($data['parent_id'] ?? false) && Product::find($data['parent_id']) && Product::find($data['parent_id'])->activeProductFilters->contains($productFilter->id)))) {
                    foreach ($productFilter->productFilterOptions as $productFilterOption) {
                        if ($data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"] ?? false) {
                            $this->record->productFilters()->attach($productFilter->id, ['product_filter_option_id' => $productFilterOption->id]);
                        }
                        if ($data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}_enabled"] ?? false) {
                            $this->record->enabledProductFilterOptions()->attach($productFilter->id, ['product_filter_option_id' => $productFilterOption->id]);
                        }
                    }
                }
            }
        }

        foreach ($productFilters as $productFilter) {
            unset($data["product_filter_{$productFilter->id}"]);
            unset($data["product_filter_{$productFilter->id}_use_for_variations"]);
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                unset($data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"]);
                unset($data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}_enabled"]);
            }
        }

        $productCharacteristics = ProductCharacteristics::get();
        foreach ($productCharacteristics as $productCharacteristic) {
            if (isset($data["product_characteristic_{$productCharacteristic->id}_{$this->activeLocale}"])) {
                $thisProductCharacteristic = ProductCharacteristic::where('product_id', $this->record->id)->where('product_characteristic_id', $productCharacteristic->id)->first();
                if (! $thisProductCharacteristic) {
                    $thisProductCharacteristic = new ProductCharacteristic();
                    $thisProductCharacteristic->product_id = $this->record->id;
                    $thisProductCharacteristic->product_characteristic_id = $productCharacteristic->id;
                }
                $thisProductCharacteristic->setTranslation('value', $this->activeLocale, $data["product_characteristic_{$productCharacteristic->id}_{$this->activeLocale}"]);
                $thisProductCharacteristic->save();
                unset($data["product_characteristic_$productCharacteristic->id"]);
            } else {
                $thisProductCharacteristic = ProductCharacteristic::where('product_id', $this->record->id)->where('product_characteristic_id', $productCharacteristic->id)->first();
                if ($thisProductCharacteristic) {
                    $thisProductCharacteristic->setTranslation('value', $this->activeLocale, null);
                    $thisProductCharacteristic->save();
                }
            }
        }

        foreach ($data as $key => $dataItem) {
            if (str($key)->contains('product_characteristic_')) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function mutateFormDataBeforeFill($data): array
    {
        $productFilters = ProductFilter::with(['productFilterOptions'])->get();

        if ($this->record->parent) {
            $activeProductFilters = $this->record->parent->activeProductFilters;
        } else {
            $activeProductFilters = $this->record->activeProductFilters;
        }

        foreach ($productFilters as $productFilter) {
            $data["product_filter_$productFilter->id"] = (bool)$activeProductFilters->contains($productFilter->id);
            $data["product_filter_{$productFilter->id}_use_for_variations"] = $activeProductFilters->contains($productFilter->id) ? ($this->record->activeProductFilters()->wherePivot('product_filter_id', $productFilter->id)->first()->pivot->use_for_variations ?? 0) : 0;

            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                $data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"] = $this->record->productFilters()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $productFilterOption->id)->exists();
                $data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}_enabled"] = $this->record->enabledProductFilterOptions()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $productFilterOption->id)->exists();
            }
        }

        $productCharacteristics = ProductCharacteristics::get();

        foreach (Locales::getLocales() as $locale) {
            foreach ($productCharacteristics as $productCharacteristic) {
                $data["product_characteristic_{$productCharacteristic->id}_{$locale['id']}"] = $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->exists() ? $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first()->getTranslation('value', $locale['id']) : null;
            }
        }

        return $data;
    }

    //    public function afterFill(): void
    //    {
    //        $productFilters = ProductFilter::with(['productFilterOptions'])->get();
    //
    //        if ($this->record->parent) {
    //            $activeProductFilters = $this->record->parent->activeProductFilters;
    //        } else {
    //            $activeProductFilters = $this->record->activeProductFilters;
    //        }
    //
    //        foreach ($productFilters as $productFilter) {
    //            $this->data["product_filter_$productFilter->id"] = (bool)$activeProductFilters->contains($productFilter->id);
    //            $this->data["product_filter_{$productFilter->id}_use_for_variations"] = $activeProductFilters->contains($productFilter->id) ? ($this->record->activeProductFilters()->wherePivot('product_filter_id', $productFilter->id)->first()->pivot->use_for_variations ?? 0) : 0;
    //
    //            foreach ($productFilter->productFilterOptions as $productFilterOption) {
    //                $this->data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"] = $this->record->productFilters()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $productFilterOption->id)->exists();
    //            }
    //        }
    //
    //        $productCharacteristics = ProductCharacteristics::get();
    //
    //        foreach ($productCharacteristics as $productCharacteristic) {
    //            $this->data["product_characteristic_$productCharacteristic->id"] = $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->exists() ? $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first()->getTranslation('value', $this->activeLocale) : null;
    //        }
    //    }

    public function getBreadcrumbs(): array
    {
        if (! $this->record->parent) {
            return parent::getBreadcrumbs();
        }

        $breadcrumbs = parent::getBreadcrumbs();
        $breadcrumbs = array_merge([route('filament.dashed.resources.products.edit', [$this->record->parent->id]) => "{$this->record->parent->name}"], $breadcrumbs);
        $breadcrumbs = array_merge([route('filament.dashed.resources.products.index') => "Producten"], $breadcrumbs);

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        $buttons = [];

        if ($this->record->type != 'variable' || $this->record->parent_id) {
            $buttons[] = Action::make('Bekijk product')
                ->url($this->record->getUrl())
                ->openUrlInNewTab();
            $buttons[] = Action::make('Dupliceer product')
                ->action('duplicateProduct')
                ->color('warning');
        }

        $buttons[] = LocaleSwitcher::make();
        $buttons[] = DeleteAction::make();

        return $buttons;
    }

    public function duplicateProduct()
    {
        $newProduct = $this->record->replicate();
        $newProduct->purchases = 0;
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
        $newProduct->bundleProducts()->sync($this->record->bundleProducts);

        foreach (DB::table('dashed__product_characteristic')->where('product_id', $this->record->id)->whereNull('deleted_at')->get() as $productCharacteristic) {
            DB::table('dashed__product_characteristic')->insert([
                'product_id' => $newProduct->id,
                'product_characteristic_id' => $productCharacteristic->product_characteristic_id,
                'value' => $productCharacteristic->value,
            ]);
        }

        foreach (DB::table('dashed__product_filter')->where('product_id', $this->record->id)->get() as $productFilter) {
            DB::table('dashed__product_filter')->insert([
                'product_id' => $newProduct->id,
                'product_filter_id' => $productFilter->product_filter_id,
                'product_filter_option_id' => $productFilter->product_filter_option_id,
            ]);
        }

        foreach (DB::table('dashed__product_suggested_product')->where('product_id', $this->record->id)->get() as $suggestedProduct) {
            DB::table('dashed__product_suggested_product')->insert([
                'product_id' => $newProduct->id,
                'suggested_product_id' => $suggestedProduct->suggested_product_id,
                'order' => $suggestedProduct->order,
            ]);
        }

        foreach (DB::table('dashed__product_extras')->where('product_id', $this->record->id)->whereNull('deleted_at')->get() as $productExtra) {
            $newProductExtra = new ProductExtra();
            $newProductExtra->product_id = $newProduct->id;
            foreach (json_decode($productExtra->name, true) as $locale => $name) {
                $newProductExtra->setTranslation('name', $locale, $name);
            }
            $newProductExtra->type = $productExtra->type;
            $newProductExtra->required = $productExtra->required;
            $newProductExtra->save();

            foreach (DB::table('dashed__product_extra_options')->where('product_extra_id', $productExtra->id)->whereNull('deleted_at')->get() as $productExtraOption) {
                DB::table('dashed__product_extra_options')->insert([
                    'product_extra_id' => $newProductExtra->id,
                    'value' => $productExtraOption->value,
                    'price' => $productExtraOption->price,
                ]);
            }
        }

        UpdateProductInformationJob::dispatch($newProduct);

        return redirect(route('filament.dashed.resources.products.edit', [$newProduct]));
    }
}
