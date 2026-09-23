<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteTotals;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * De total-kolom is een gecachte waarde voor de lijst; de waarheid staat in
     * de regels. Na elke opslag opnieuw uitrekenen.
     */
    protected function afterSave(): void
    {
        $quote = $this->record->fresh();
        $quote->total = QuoteTotals::for($quote)->total;
        $quote->saveQuietly();
    }
}
