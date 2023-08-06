<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;

class CreateProductCategory extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        while (ProductCategory::where('slug->' . $this->activeFormLocale, $data['slug'])->count()) {
            $data['slug'] .= Str::random(1);
        }

        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];
        //        $content = $data['content'];
        //        $data['content'] = null;
        //        $data['content'][$this->activeFormLocale] = $content;

        return $data;
    }
}
