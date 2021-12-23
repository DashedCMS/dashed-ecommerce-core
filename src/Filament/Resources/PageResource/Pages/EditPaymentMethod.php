<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;

class EditPaymentMethod extends EditRecord
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;
}
