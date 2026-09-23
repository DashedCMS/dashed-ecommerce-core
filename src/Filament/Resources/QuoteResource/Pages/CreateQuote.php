<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource;

class CreateQuote extends CreateRecord
{
    protected static string $resource = QuoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }

    /** Zelfde reden als in EditQuote: total is een gecachte waarde voor de lijst. */
    protected function afterCreate(): void
    {
        $this->record->fresh()->recalculateTotal();
    }
}
