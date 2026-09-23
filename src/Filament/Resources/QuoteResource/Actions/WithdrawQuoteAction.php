<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteRevision;

class WithdrawQuoteAction
{
    public static function make(Quote $quote): Action
    {
        return Action::make('withdrawQuote')
            ->label(__('Intrekken'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn () => $quote->status === Quote::STATUS_SENT)
            ->requiresConfirmation()
            ->modalDescription(__('De klant kan de offerte daarna niet meer accepteren.'))
            ->action(function () use ($quote) {
                QuoteRevision::withdraw($quote);

                Notification::make()->title(__('De offerte is ingetrokken'))->success()->send();
            });
    }
}
