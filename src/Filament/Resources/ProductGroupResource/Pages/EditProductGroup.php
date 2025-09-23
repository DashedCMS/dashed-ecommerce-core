<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\ProductFilter;
use Dashed\DashedEcommerceCore\Classes\ProductCategories;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristic;
use Dashed\DashedEcommerceCore\Models\ProductCharacteristics;
use Dashed\DashedCore\Filament\Concerns\HasEditableCMSActions;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;

class EditProductGroup extends EditRecord
{
    use HasEditableCMSActions;

    protected static string $resource = ProductGroupResource::class;

    protected static ?string $title = 'Bewerk product groep';

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        $selectedProductCategories = ProductCategories::getFromIdsWithParents($this->record->productCategories()->pluck('product_category_id'));

        $this->record->productCategories()->sync($selectedProductCategories);

        $productFilters = ProductFilter::with(['productFilterOptions'])->get();

        $this->record->activeProductFilters()->detach();
        foreach ($productFilters as $productFilter) {
            if (in_array($productFilter->id, $data['productFilters'])) {
                $this->record->activeProductFilters()->attach($productFilter->id, [
                    'use_for_variations' => $data["product_filter_{$productFilter->id}_use_for_variations"],
                ]);
            }
        }

        $this->record->enabledProductFilterOptions()->detach();
        foreach ($productFilters as $productFilter) {
            foreach ($data['product_filter_options_' . $productFilter->id] ?? [] as $optionId) {
                $this->record->enabledProductFilterOptions()->attach($productFilter->id, ['product_filter_option_id' => $optionId]);
            }
        }

        unset($data['productFilters']);
        foreach ($productFilters as $productFilter) {
            unset($data['product_filter_options_' . $productFilter->id]);
            unset($data["product_filter_{$productFilter->id}_use_for_variations"]);
        }

        $productCharacteristics = ProductCharacteristics::get();
        foreach ($productCharacteristics as $productCharacteristic) {
            if (isset($data["product_characteristic_{$productCharacteristic->id}_{$this->activeLocale}"])) {
                $thisProductCharacteristic = ProductCharacteristic::where('product_group_id', $this->record->id)->where('product_characteristic_id', $productCharacteristic->id)->first();
                if (! $thisProductCharacteristic) {
                    $thisProductCharacteristic = new ProductCharacteristic();
                    $thisProductCharacteristic->product_group_id = $this->record->id;
                    $thisProductCharacteristic->product_characteristic_id = $productCharacteristic->id;
                }
                $thisProductCharacteristic->setTranslation('value', $this->activeLocale, $data["product_characteristic_{$productCharacteristic->id}_{$this->activeLocale}"]);
                $thisProductCharacteristic->save();
                unset($data["product_characteristic_$productCharacteristic->id"]);
            } else {
                $thisProductCharacteristic = ProductCharacteristic::where('product_group_id', $this->record->id)->where('product_characteristic_id', $productCharacteristic->id)->first();
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

        foreach ($data['new_images'] ?? [] as $key => $image) {
            $url = Storage::disk('dashed')->url($image);

            $data['images'][] = mediaHelper()->uploadFromPath($url, 'producten', true);

            Storage::disk('dashed')->delete($image);
        }

        unset($data['new_images']);

        $record->update($data);

        return $record;
    }

    public function mutateFormDataBeforeFill($data): array
    {
        $productFilters = ProductFilter::with(['productFilterOptions'])->get();
        $activeProductFilters = $this->record->activeProductFilters;

        $data['productFilters'] = $this->record->activeProductFilters()->pluck('product_filter_id')->toArray();

        foreach ($productFilters as $productFilter) {
            $data["product_filter_{$productFilter->id}_use_for_variations"] = $activeProductFilters->contains($productFilter->id) ? ($this->record->activeProductFilters()->wherePivot('product_filter_id', $productFilter->id)->first()->pivot->use_for_variations ?? 0) : 0;
            $data['product_filter_options_' . $productFilter->id] = $this->record->enabledProductFilterOptions()->where('product_filter_id', $productFilter->id)->pluck('product_filter_option_id')->toArray();
        }

        $productCharacteristics = ProductCharacteristics::get();

        foreach (Locales::getLocales() as $locale) {
            foreach ($productCharacteristics as $productCharacteristic) {
                $data["product_characteristic_{$productCharacteristic->id}_{$locale['id']}"] = $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->exists() ? $this->record->productCharacteristics()->where('product_characteristic_id', $productCharacteristic->id)->first()->getTranslation('value', $locale['id']) : null;
            }
        }

        return $data;
    }

    protected function getActions(): array
    {
        $buttons = [];

        $buttons[] = self::viewAction();

        $buttons[] = Action::make('Dupliceer')
            ->action('duplicateProductGroup')
            ->color('warning');

        $buttons[] = self::translateAction();
        $buttons[] = LocaleSwitcher::make();
        $buttons[] = DeleteAction::make();

        return $buttons;
    }

    public function duplicateProductGroup()
    {
        $newProductGroup = $this->record->replicate();
        $newProductGroup->total_purchases = 0;
        $newProductGroup->total_stock = 0;
        foreach (Locales::getLocales() as $locale) {
            $newProductGroup->setTranslation('slug', $locale['id'], $newProductGroup->getTranslation('slug', $locale['id']));
            while (ProductGroup::where('slug->' . $locale['id'], $newProductGroup->getTranslation('slug', $locale['id']))->count()) {
                $newProductGroup->setTranslation('slug', $locale['id'], $newProductGroup->getTranslation('slug', $locale['id']) . Str::random(1));
            }
        }
        $newProductGroup->save();

        $this->record->load('activeProductFilters', 'enabledProductFilterOptions', 'productCharacteristics', 'productCategories', 'suggestedProducts', 'crossSellProducts', 'tabs', 'productExtras');

        $newProductGroup->activeProductFilters()->sync($this->record->activeProductFilters);
        $newProductGroup->productCategories()->sync($this->record->productCategories);
        $newProductGroup->tabs()->sync($this->record->tabs);

        foreach (DB::table('dashed__product_characteristic')->where('product_group_id', $this->record->id)->whereNull('deleted_at')->get() as $productCharacteristic) {
            DB::table('dashed__product_characteristic')->insert([
                'product_group_id' => $newProductGroup->id,
                'product_characteristic_id' => $productCharacteristic->product_characteristic_id,
                'value' => $productCharacteristic->value,
            ]);
        }

        foreach (DB::table('dashed__product_enabled_filter_options')->where('product_group_id', $this->record->id)->get() as $productFilter) {
            DB::table('dashed__product_enabled_filter_options')->insert([
                'product_group_id' => $newProductGroup->id,
                'product_filter_id' => $productFilter->product_filter_id,
                'product_filter_option_id' => $productFilter->product_filter_option_id,
            ]);
        }

        foreach (DB::table('dashed__product_suggested_product')->where('product_group_id', $this->record->id)->get() as $suggestedProduct) {
            DB::table('dashed__product_suggested_product')->insert([
                'product_group_id' => $newProductGroup->id,
                'suggested_product_id' => $suggestedProduct->suggested_product_id,
                'order' => $suggestedProduct->order,
            ]);
        }

        foreach (DB::table('dashed__product_crosssell_product')->where('product_group_id', $this->record->id)->get() as $crossSellProduct) {
            DB::table('dashed__product_crosssell_product')->insert([
                'product_group_id' => $newProductGroup->id,
                'crosssell_product_id' => $crossSellProduct->crosssell_product_id,
                'order' => $crossSellProduct->order,
            ]);
        }

        foreach (DB::table('dashed__product_extras')->where('product_group_id', $this->record->id)->whereNull('deleted_at')->get() as $productExtra) {
            $newProductGroupExtra = new ProductExtra();
            $newProductGroupExtra->product_group_id = $newProductGroup->id;
            foreach (json_decode($productExtra->name, true) as $locale => $name) {
                $newProductGroupExtra->setTranslation('name', $locale, $name);
            }
            $newProductGroupExtra->type = $productExtra->type;
            $newProductGroupExtra->required = $productExtra->required;
            $newProductGroupExtra->save();

            foreach (DB::table('dashed__product_extra_options')->where('product_extra_id', $productExtra->id)->whereNull('deleted_at')->get() as $productExtraOption) {
                DB::table('dashed__product_extra_options')->insert([
                    'product_extra_id' => $newProductGroupExtra->id,
                    'value' => $productExtraOption->value,
                    'price' => $productExtraOption->price,
                ]);
            }
        }

        if ($this->record->customBlocks) {
            $newCustomBlock = $this->record->customBlocks->replicate();
            $newCustomBlock->blockable_id = $newProductGroup->id;
            $newCustomBlock->save();
        }

        if ($this->record->metaData) {
            $newMetaData = $this->record->metaData->replicate();
            $newMetaData->metadatable_id = $newProductGroup->id;
            $newMetaData->save();
        }

        UpdateProductInformationJob::dispatch($newProductGroup)->onQueue('ecommerce');

        return redirect(route('filament.dashed.resources.product-groups.edit', [$newProductGroup]));
    }
}
