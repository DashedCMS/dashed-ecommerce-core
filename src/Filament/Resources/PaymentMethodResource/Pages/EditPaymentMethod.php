<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource\Pages;

use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource;

class EditPaymentMethod extends EditRecord
{
    use Translatable;

    protected static string $resource = PaymentMethodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
