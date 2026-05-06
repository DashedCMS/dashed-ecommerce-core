<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Support\Facades\Storage;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use Dashed\DashedCore\Filament\Actions\AnalyzeSeoAction;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristic;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use Dashed\DashedCore\Filament\Concerns\HasEditableCMSActions;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\Product\ProductOpenOrdersWidget;

class EditProduct extends EditRecord
{
    use HasEditableCMSActions;

    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Bewerk product';

    protected function getFooterWidgets(): array
    {
        return [
            ProductOpenOrdersWidget::class,
        ];
    }

    protected function getFooterWidgetsColumns(): int|string|array
    {
        return 1;
    }

    public function mount($record): void
    {
        $thisRecord = $this->resolveRecord($record);
        //        foreach (Locales::getLocales() as $locale) {
        //            if (! $thisRecord->images) {
        //                $images = $thisRecord->getTranslation('images', $locale['id']);
        //                if (! $images) {
        //                    if (! is_array($images)) {
        //                        $thisRecord->setTranslation('images', $locale['id'], []);
        //                        $thisRecord->save();
        //                    }
        //                }
        //            }
        //        }

        parent::mount($record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        $selectedProductCategories = ProductCategories::getFromIdsWithParents($this->record->productCategories()->pluck('product_category_id'));

        $this->record->productCategories()->sync($selectedProductCategories);

        $productFilters = $this->record->productGroup->activeProductFilters;

        foreach ($productFilters as $productFilter) {
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                unset($data["product_filter_{$productFilter->id}_option_{$productFilterOption->id}"]);
            }
        }

        //        $productCharacteristics = ProductCharacteristics::get();
        //        foreach ($productCharacteristics as $productCharacteristic) {
        //            if (isset($data["product_characteristic_{$productCharacteristic->id}_{$this->activeLocale}"])) {
        //                $thisProductCharacteristic = ProductCharacteristic::where('product_id', $this->record->id)->where('product_characteristic_id', $productCharacteristic->id)->first();
        //                if (! $thisProductCharacteristic) {
        //                    $thisProductCharacteristic = new ProductCharacteristic();
        //                    $thisProductCharacteristic->product_id = $this->record->id;
        //                    $thisProductCharacteristic->product_characteristic_id = $productCharacteristic->id;
        //                }
        //                $thisProductCharacteristic->setTranslation('value', $this->activeLocale, $data["product_characteristic_{$productCharacteristic->id}_{$this->activeLocale}"]);
        //                $thisProductCharacteristic->save();
        //                dump($data["product_characteristic_{$productCharacteristic->id}_{$this->activeLocale}"]);
        //                unset($data["product_characteristic_$productCharacteristic->id"]);
        //            } else {
        //                $thisProductCharacteristic = ProductCharacteristic::where('product_id', $this->record->id)->where('product_characteristic_id', $productCharacteristic->id)->first();
        //                if ($thisProductCharacteristic) {
        //                    dump('empty');
        //                    $thisProductCharacteristic->setTranslation('value', $this->activeLocale, null);
        //                    $thisProductCharacteristic->save();
        //                }
        //            }
        //        }

        //        foreach ($data as $key => $dataItem) {
        //            if (str($key)->contains('product_characteristic_')) {
        //                unset($data[$key]);
        //            }
        //        }
        //        unset($data['productCharacteristics']);
        unset($data['productExtras']);

        foreach ($data['new_images'] ?? [] as $key => $image) {
            $url = Storage::disk('dashed')->url($image);

            $data['images'][] = mediaHelper()->uploadFromPath($url, 'producten', true);

            Storage::disk('dashed')->delete($image);
        }

        unset($data['new_images']);

        return $data;
    }

    public function mutateFormDataBeforeFill($data): array
    {
        $this->record->load(['productGroup.activeProductFilters.productFilterOptions', 'productFilters', 'productCharacteristics']);

        $activeFilters = $this->record->productGroup->activeProductFilters;
        $existingFilterKeys = $this->record->productFilters
            ->map(fn ($pf) => $pf->id.'_'.$pf->pivot->product_filter_option_id)
            ->flip()
            ->all();

        foreach ($activeFilters as $productFilter) {
            foreach ($productFilter->productFilterOptions as $productFilterOption) {
                $key = "product_filter_{$productFilter->id}_option_{$productFilterOption->id}";
                $data[$key] = isset($existingFilterKeys[$productFilter->id.'_'.$productFilterOption->id]);
            }
        }

        $productCharacteristics = ProductCharacteristics::get();
        $existingCharacteristics = $this->record->productCharacteristics->keyBy('product_characteristic_id');

        foreach (Locales::getLocales() as $locale) {
            foreach ($productCharacteristics as $productCharacteristic) {
                $existing = $existingCharacteristics->get($productCharacteristic->id);
                $data["product_characteristic_{$productCharacteristic->id}_{$locale['id']}"] = $existing?->getTranslation('value', $locale['id']);
            }
        }

        return $data;
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $breadcrumbs = array_merge([route('filament.dashed.resources.product-groups.edit', [$this->record->productGroup->id]) => "{$this->record->productGroup->name}"], $breadcrumbs);
        $breadcrumbs = array_merge([route('filament.dashed.resources.products.index') => 'Producten'], $breadcrumbs);

        return $breadcrumbs;
    }

    protected function getActions(): array
    {
        $buttons = [];

        $buttons[] = self::viewAction();

        if ($this->record->type != 'variable' || $this->record->parent_id) {
            $buttons[] = Action::make('Dupliceer')
                ->action('duplicateProduct')
                ->color('warning');
        }

        if (class_exists(AnalyzeSeoAction::class)) {
            $buttons[] = AnalyzeSeoAction::make();
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
        $newProduct->sku = 'SKU'.rand(10000, 99999);
        foreach (Locales::getLocales() as $locale) {
            $newProduct->setTranslation('slug', $locale['id'], $newProduct->getTranslation('slug', $locale['id']));
            while (Product::where('slug->'.$locale['id'], $newProduct->getTranslation('slug', $locale['id']))->count()) {
                $newProduct->setTranslation('slug', $locale['id'], $newProduct->getTranslation('slug', $locale['id']).Str::random(1));
            }
        }
        $newProduct->save();

        $this->record->load('productCategories', 'shippingClasses', 'productFilters', 'productCharacteristics', 'productExtras');

        $newProduct->productCategories()->sync($this->record->productCategories);
        $newProduct->shippingClasses()->sync($this->record->shippingClasses);
        $newProduct->bundleProducts()->sync($this->record->bundleProducts);

        $characteristics = DB::table('dashed__product_characteristic')->where('product_id', $this->record->id)->whereNull('deleted_at')->get();
        if ($characteristics->isNotEmpty()) {
            DB::table('dashed__product_characteristic')->insert($characteristics->map(fn ($c) => [
                'product_id' => $newProduct->id,
                'product_characteristic_id' => $c->product_characteristic_id,
                'value' => $c->value,
            ])->all());
        }

        $filters = DB::table('dashed__product_filter')->where('product_id', $this->record->id)->get();
        if ($filters->isNotEmpty()) {
            DB::table('dashed__product_filter')->insert($filters->map(fn ($f) => [
                'product_id' => $newProduct->id,
                'product_filter_id' => $f->product_filter_id,
                'product_filter_option_id' => $f->product_filter_option_id,
            ])->all());
        }

        $suggested = DB::table('dashed__product_suggested_product')->where('product_id', $this->record->id)->get();
        if ($suggested->isNotEmpty()) {
            DB::table('dashed__product_suggested_product')->insert($suggested->map(fn ($s) => [
                'product_id' => $newProduct->id,
                'suggested_product_id' => $s->suggested_product_id,
                'order' => $s->order,
            ])->all());
        }

        $crossSell = DB::table('dashed__product_crosssell_product')->where('product_id', $this->record->id)->get();
        if ($crossSell->isNotEmpty()) {
            DB::table('dashed__product_crosssell_product')->insert($crossSell->map(fn ($c) => [
                'product_id' => $newProduct->id,
                'crosssell_product_id' => $c->crosssell_product_id,
                'order' => $c->order,
            ])->all());
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

        if ($this->record->customBlocks) {
            $newCustomBlock = $this->record->customBlocks->replicate();
            $newCustomBlock->blockable_id = $newProduct->id;
            $newCustomBlock->save();
        }

        if ($this->record->metaData) {
            $newMetaData = $this->record->metaData->replicate();
            $newMetaData->metadatable_id = $newProduct->id;
            $newMetaData->save();
        }

        UpdateProductInformationJob::dispatch($newProduct->productGroup)->onQueue('ecommerce');

        return redirect(route('filament.dashed.resources.products.edit', [$newProduct]));
    }
}
