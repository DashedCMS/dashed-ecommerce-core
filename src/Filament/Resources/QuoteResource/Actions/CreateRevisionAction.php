<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteRevision;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource;

class CreateRevisionAction
{
    public static function make(Quote $quote): Action
    {
        return Action::make('createRevision')
            ->label(__('Nieuwe revisie'))
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->visible(fn () => $quote->sent_at !== null)
            ->requiresConfirmation()
            ->modalHeading(__('Nieuwe revisie maken?'))
            ->modalDescription(__('De huidige versie komt op vervangen te staan en je werkt verder in een kopie.'))
            ->action(function ($livewire) use ($quote) {
                $revisie = QuoteRevision::create($quote);

                Notification::make()
                    ->title(__('Revisie :versie aangemaakt', ['versie' => $revisie->version]))
                    ->success()
                    ->send();

                $livewire->redirect(QuoteResource::getUrl('edit', ['record' => $revisie]));
            });
    }
}
