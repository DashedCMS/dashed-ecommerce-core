<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;

class CreatePaymentMethod extends CreateRecord
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;
}
