<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;

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
