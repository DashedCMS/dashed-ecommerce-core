<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\PageResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Filament\Resources\PageResource;
use Qubiqx\QcommerceCore\Models\Page;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\PaymentMethodResource;

class CreatePaymentMethod extends CreateRecord
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;
}
