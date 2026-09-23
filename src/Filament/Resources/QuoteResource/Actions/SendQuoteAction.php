<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteSender;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccount;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountOverride;

class SendQuoteAction
{
    public static function make(Quote $quote): Action
    {
        return Action::make('sendQuote')
            ->label($quote->sent_at ? __('Opnieuw versturen') : __('Versturen'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->button()
            ->visible(fn () => in_array($quote->status, [Quote::STATUS_CONCEPT, Quote::STATUS_SENT], true))
            ->modalHeading(__('Offerte versturen'))
            ->modalSubmitActionLabel(__('Versturen'))
            ->form([
                TextInput::make('email')
                    ->label(__('Ontvanger'))
                    ->email()
                    ->required()
                    ->default($quote->email),
                TextInput::make('cc')
                    ->label(__('CC'))
                    ->email(),
                Textarea::make('message')
                    ->label(__('Begeleidend bericht'))
                    ->rows(4)
                    ->helperText(__('Komt in de mail te staan, niet op de PDF')),
            ])
            ->action(function (array $data) use ($quote) {
                // Op rekening mag alleen als die klant er ook aan gekoppeld is.
                // Dat moet je weten voordat de offerte de deur uit gaat, want
                // "geen methode op rekening gekoppeld" is nooit door te zetten.
                if ($quote->payment_route === Quote::ROUTE_ON_ACCOUNT && ! self::mayOrderOnAccount($quote)) {
                    Notification::make()
                        ->title(__('Deze klant kan niet op rekening bestellen'))
                        ->body(__('Koppel een betaalmethode op rekening aan dit klantaccount, of kies vooraf betalen.'))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                QuoteSender::send($quote, $data['email'], $data['cc'] ?? null, $data['message'] ?? null);

                Notification::make()
                    ->title(__('De offerte is verstuurd'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Alleen "geen methode op rekening gekoppeld" houdt het versturen tegen.
     * Een limiet of een openstaande factuur kan tegen de tijd dat de klant
     * antwoordt weer opgelost zijn, en dat blokkeert het akkoord ook niet.
     */
    private static function mayOrderOnAccount(Quote $quote): bool
    {
        $customer = $quote->user;
        if (! $customer) {
            return false;
        }

        $check = OnAccountOverride::precheck($customer, (float) $quote->total, (string) $quote->site_id);

        return $check->allowed || $check->reason !== OnAccount::NOT_ENABLED;
    }
}
