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
        return [
            \Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\SendQuoteAction::make($this->record),
            \Filament\Actions\Action::make('preview')
                ->label(__('Voorbeeld'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function () {
                    \Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf::store($this->record->fresh());
                    $this->redirect(\Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf::downloadUrl($this->record->fresh()), navigate: false);
                }),
            DeleteAction::make(),
        ];
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
