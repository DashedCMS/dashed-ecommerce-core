<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource;

class CreateProductFilterOption extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductFilterOptionResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeFill($data)
    {
        $data['product_filter_id'] = request()->get('productFilterId');
        return $data;
    }
}
