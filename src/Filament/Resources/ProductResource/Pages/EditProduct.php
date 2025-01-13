<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Dashed\DashedCore\Classes\Locales;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristic;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use Dashed\DashedCore\Filament\Concerns\HasEditableCMSActions;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

class EditProduct extends EditRecord
{
    use HasEditableCMSActions;

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
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        $selectedProductCategories = ProductCategories::getFromIdsWithParents($this->record->productCategories()->pluck('product_category_id'));

        $this->record->productCategories()->sync($selectedProductCategories);

        $productFilters = $this->record->productGroup->activeProductFilters;

        $this->record->productFilters()->detach();
        foreach ($productFilters as $productFilter) {
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                if ($data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"] ?? false) {
                    $this->record->productFilters()->attach($productFilter->id, ['product_filter_option_id' => $productFilterOption->id]);
                }
            }
        }

        foreach ($productFilters as $productFilter) {
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                unset($data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"]);
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
        unset($data['productCharacteristics']);
        unset($data['productExtras']);

        return $data;
    }

    public function mutateFormDataBeforeFill($data): array
    {
        $productFilters = $this->record->productGroup->activeProductFilters;

        foreach ($this->record->productGroup->activeProductFilters as $productFilter) {
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                $key = "product_filter_{$productFilter->id}_option_{$productFilterOption->id}";
                $data[$key] = $this->record->productFilters()->where('product_filter_id', $productFilter->id)->where('product_filter_option_id', $productFilterOption->id)->exists();
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

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $breadcrumbs = array_merge([route('filament.dashed.resources.product-groups.edit', [$this->record->productGroup->id]) => "{$this->record->productGroup->name}"], $breadcrumbs);
        $breadcrumbs = array_merge([route('filament.dashed.resources.products.index') => "Producten"], $breadcrumbs);

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        $buttons = [];

        $buttons[] = self::viewAction();

        if ($this->record->type != 'variable' || $this->record->parent_id) {
            $buttons[] = Action::make('Dupliceer product')
                ->action('duplicateProduct')
                ->color('warning');
        }

        $buttons[] = self::translateAction();
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

        $this->record->load('productCategories', 'shippingClasses', 'productFilters', 'productCharacteristics', 'productExtras');

        $newProduct->productCategories()->sync($this->record->productCategories);
        $newProduct->shippingClasses()->sync($this->record->shippingClasses);
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

        foreach (DB::table('dashed__product_crosssell_product')->where('product_id', $this->record->id)->get() as $crossSellProduct) {
            DB::table('dashed__product_crosssell_product')->insert([
                'product_id' => $newProduct->id,
                'crosssell_product_id' => $crossSellProduct->crosssell_product_id,
                'order' => $crossSellProduct->order,
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

        UpdateProductInformationJob::dispatch($newProduct->productGroup);

        return redirect(route('filament.dashed.resources.products.edit', [$newProduct]));
    }
}
