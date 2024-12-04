<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductTabResource;

class CreateProductTab extends CreateRecord
{
    use Translatable;

    protected static string $resource = ProductTabResource::class;

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
