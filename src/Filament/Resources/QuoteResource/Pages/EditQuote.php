<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Services\Quotes\QuotePdf;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\SendQuoteAction;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\CreateRevisionAction;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\WithdrawQuoteAction;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions\MarkQuoteAnsweredAction;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendQuoteAction::make($this->record),
            Action::make('preview')
                ->label(__('Voorbeeld'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(function () {
                    // Naar het voorbeeldpad, nooit naar het verstuurde document:
                    // een offerte die de deur uit is moet blijven kloppen met de
                    // PDF die de klant in zijn mail heeft.
                    QuotePdf::storePreview($this->record->fresh());
                    $this->redirect(QuotePdf::previewDownloadUrl($this->record->fresh()), navigate: false);
                }),
            Action::make('acceptedPdf')
                ->label(__('Akkoord-PDF'))
                ->icon('heroicon-o-document-check')
                ->color('gray')
                ->visible(fn () => QuotePdf::downloadUrl($this->record, accepted: true) !== null)
                ->action(fn () => $this->redirect(QuotePdf::downloadUrl($this->record->fresh(), accepted: true), navigate: false)),
            MarkQuoteAnsweredAction::make($this->record),
            CreateRevisionAction::make($this->record),
            WithdrawQuoteAction::make($this->record),
            DeleteAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        if (! QuoteResource::isLocked($this->record)) {
            return null;
        }

        return __('Deze offerte is verstuurd en staat vast. Maak een nieuwe revisie om iets te wijzigen.');
    }

    /**
     * Een verstuurde offerte is niet meer te bewerken, dus staat er ook geen
     * opslaanknop onder. Het formulier zelf is al uitgezet in
     * QuoteResource::form(); zonder dit staat er een knop die niets doet.
     */
    protected function getFormActions(): array
    {
        if (QuoteResource::isLocked($this->record)) {
            return [];
        }

        return parent::getFormActions();
    }

    /**
     * Het uitzetten van het formulier is de zichtbare kant; dit is het slot.
     * Filament 4 laat een uitgezet veld wel meedehydrateren, dus zonder deze
     * halt kan een verzoek de staat alsnog wegschrijven, en dan klopt de PDF
     * die de klant heeft niet meer met de offerte erachter.
     */
    protected function beforeSave(): void
    {
        if (! QuoteResource::isLocked($this->record)) {
            return;
        }

        Notification::make()
            ->title(__('Deze offerte is verstuurd en staat vast'))
            ->body(__('Maak een nieuwe revisie om iets te wijzigen.'))
            ->danger()
            ->send();

        $this->halt();
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
