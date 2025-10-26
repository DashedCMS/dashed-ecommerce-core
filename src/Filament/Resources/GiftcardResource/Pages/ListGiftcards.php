<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource\Pages;

use Dashed\DashedEcommerceCore\Filament\Resources\GiftcardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\DiscountCodeResource;

class ListGiftcards extends ListRecords
{
    protected static string $resource = GiftcardResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
