<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource;

class CreateShippingMethod extends CreateRecord
{
    use Translatable;

    protected static string $resource = ShippingMethodResource::class;

    public function beforeFill()
    {
        $this->data['variables'] = [];
    }
}
