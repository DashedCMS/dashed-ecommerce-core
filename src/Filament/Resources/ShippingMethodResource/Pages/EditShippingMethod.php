<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ShippingMethodResource;

class EditShippingMethod extends EditRecord
{
    use Translatable;

    protected static string $resource = ShippingMethodResource::class;
}
