<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Redirect;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;

class EditProductCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        while (ProductCategory::where('id', '!=', $this->record->id)->where('slug->' . $this->activeFormLocale, $data['slug'])->count()) {
            $data['slug'] .= Str::random(1);
        }

        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        //        $content = $data['content'];
        //        $data['content'] = $this->record->content;
        //        $data['content'][$this->activeFormLocale] = $content;

        Redirect::handleSlugChange($this->record->getTranslation('slug', $this->activeFormLocale), $data['slug']);

        return $data;
    }
}
