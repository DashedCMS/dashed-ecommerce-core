<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource\Pages;

use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource;

class CreateShippingMethod extends CreateRecord
{
    use Translatable;

    protected static string $resource = ShippingMethodResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    public function beforeFill()
    {
        $this->data['variables'] = [];
    }
}
