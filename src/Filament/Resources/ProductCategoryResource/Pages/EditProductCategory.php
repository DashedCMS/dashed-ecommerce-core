<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource\Pages;

use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\ProductCategory;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductCategoryResource;

class EditProductCategory extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductCategoryResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Action::make('viewCategory')
                ->button()
                ->label('Bekijk categorie')
                ->url($this->record->getUrl())
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);

        while (ProductCategory::where('id', '!=', $this->record->id)->where('slug->' . $this->activeLocale, $data['slug'])->count()) {
            $data['slug'] .= Str::random(1);
        }

        $data['site_ids'] = $data['site_ids'] ?? [Sites::getFirstSite()['id']];

        return $data;
    }
}
