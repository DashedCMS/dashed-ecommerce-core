<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\ProductExtraResource;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterResource;

class CreateProductExtra extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductExtraResource::class;

    protected function getActions(): array
    {
        return [
          LocaleSwitcher::make(),
        ];
    }

    public function mutateFormDataBeforeCreate(array $data): array
    {
        return array_merge($data, [
            'global' => 1,
        ]);
    }
}
