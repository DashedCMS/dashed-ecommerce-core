<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingClassResource;

class CreateShippingClass extends CreateRecord
{
    use Translatable;

    protected static string $resource = ShippingClassResource::class;
}
