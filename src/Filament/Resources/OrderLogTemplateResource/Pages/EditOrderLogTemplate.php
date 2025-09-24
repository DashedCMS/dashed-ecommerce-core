<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\OrderLogTemplateResource;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class EditOrderLogTemplate extends EditRecord
{
    use Translatable;

    protected static string $resource = OrderLogTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
