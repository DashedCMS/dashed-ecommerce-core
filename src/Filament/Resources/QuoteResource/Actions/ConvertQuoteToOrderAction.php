<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Quote;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Dashed\DashedEcommerceCore\Classes\ManualPaymentPin;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteConverter;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource;
use Dashed\DashedEcommerceCore\Services\OnAccount\OnAccountOverride;

/**
 * Zet een geaccepteerde offerte om naar een bestelling, vanuit het CMS.
 * Loopt volledig door QuoteConverter::convert(), dezelfde deur als de
 * publieke offertepagina.
 */
class ConvertQuoteToOrderAction
{
    public static function make(Quote $quote): Action
    {
        return Action::make('convertQuoteToOrder')
            ->label(__('Omzetten naar bestelling'))
            ->icon('heroicon-o-shopping-cart')
            ->color('primary')
            ->button()
            ->visible(fn () => $quote->status === Quote::STATUS_ACCEPTED && ! $quote->order_id)
            ->modalHeading(__('Offerte omzetten naar bestelling'))
            ->modalSubmitActionLabel(__('Omzetten'))
            ->form([
                Radio::make('mode')
                    ->label(__('Hoe wordt er betaald?'))
                    ->options([
                        QuoteConverter::PAYMENT_LINK => $quote->email
                            ? __('Betaallink mailen naar :email', ['email' => $quote->email])
                            : __('Betaallink mailen (geen e-mailadres bekend)'),
                        QuoteConverter::ON_ACCOUNT => __('Op rekening (factuur)'),
                        QuoteConverter::DRAFT => __('Alleen de bestelling aanmaken'),
                        QuoteConverter::PAID => __('Als betaald markeren'),
                    ])
                    ->disableOptionWhen(fn (string $value) => ($value === QuoteConverter::ON_ACCOUNT && ! $quote->user_id)
                        || ($value === QuoteConverter::PAYMENT_LINK && ! $quote->email))
                    ->helperText(function () use ($quote) {
                        $meldingen = [];

                        if (! $quote->email) {
                            $meldingen[] = __('Zonder e-mailadres op de offerte kan er geen betaallink gemaild worden.');
                        }

                        if (! $quote->user_id) {
                            $meldingen[] = __('Op rekening kan alleen met een klantaccount op de offerte.');
                        }

                        return $meldingen ? implode(' ', $meldingen) : null;
                    })
                    ->default($quote->email ? QuoteConverter::PAYMENT_LINK : QuoteConverter::DRAFT)
                    ->required()
                    ->live(),
                Toggle::make('override')
                    ->label(__('Toch doorzetten als de kredietcontrole nee zegt'))
                    ->visible(fn ($get) => $get('mode') === QuoteConverter::ON_ACCOUNT && OnAccountOverride::actorMayOverride()),
                ManualPaymentPin::formField()
                    ->visible(fn ($get) => $get('mode') === QuoteConverter::PAID && ManualPaymentPin::required()),
            ])
            ->action(function (array $data, $livewire) use ($quote) {
                try {
                    $result = QuoteConverter::convert($quote, $data['mode'], [
                        'by_admin' => true,
                        'override' => (bool) ($data['override'] ?? false),
                        'pin' => $data['pin'] ?? null,
                    ]);
                } catch (LockTimeoutException) {
                    Notification::make()
                        ->title(__('De offerte wordt al omgezet, probeer het zo opnieuw'))
                        ->danger()
                        ->send();

                    return;
                }

                if (! $result->ok()) {
                    Notification::make()
                        ->title(__('De offerte is niet omgezet'))
                        ->body(__('Reden: :reden', ['reden' => (string) $result->refusalMessage()]))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()->title(__('De bestelling is aangemaakt'))->success()->send();

                $livewire->redirect(OrderResource::getUrl('view', ['record' => $result->order]));
            });
    }
}
