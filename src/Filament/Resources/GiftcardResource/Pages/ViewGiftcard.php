<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages;

use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource;

class ViewGiftcard extends ViewRecord
{
    protected static string $resource = GiftcardResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
