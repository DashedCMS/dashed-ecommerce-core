<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource\Pages;

use Illuminate\Support\Str;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\OrderResource;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Qubiqx\QcommerceEcommerceCore\Filament\Resources\ProductResource;

class CreateOrder extends CreateRecord
{
    use Translatable;

    protected static string $resource = OrderResource::class;
}
