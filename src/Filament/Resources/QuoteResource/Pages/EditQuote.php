<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
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
            \Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\MarkQuoteAnsweredAction::make($this->record),
            \Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\CreateRevisionAction::make($this->record),
            \Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\WithdrawQuoteAction::make($this->record),
            DeleteAction::make(),
        ];
    }

    /**
     * De total-kolom is een gecachte waarde voor de lijst; de waarheid staat in
     * de regels. Na elke opslag opnieuw uitrekenen.
     */
    protected function afterSave(): void
    {
        $this->record->fresh()->recalculateTotal();
    }
}
