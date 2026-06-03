<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\PriceGroupResource;

class CreatePriceGroup extends CreateRecord
{
    use PersistsPriceGroupPrices;

    protected static string $resource = PriceGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->onlyPriceGroupColumns($data);
    }

    protected function afterCreate(): void
    {
        $this->persistPriceGroupPrices($this->form->getState());
    }
}
