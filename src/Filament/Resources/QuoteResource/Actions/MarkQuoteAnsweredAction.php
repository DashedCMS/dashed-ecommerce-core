<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteAcceptance;

class MarkQuoteAnsweredAction
{
    public static function make(Quote $quote): Action
    {
        return Action::make('markQuoteAnswered')
            ->label(__('Antwoord vastleggen'))
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->visible(fn () => $quote->isAnswerable())
            ->modalHeading(__('Antwoord van de klant vastleggen'))
            ->modalDescription(__('Gebruik dit als de klant per mail of telefoon reageerde in plaats van via de offertepagina.'))
            ->form([
                Radio::make('answer')
                    ->label(__('Wat zei de klant?'))
                    ->options([
                        'accepted' => __('Akkoord'),
                        'rejected' => __('Afgewezen'),
                    ])
                    ->default('accepted')
                    ->required()
                    ->live(),
                TextInput::make('name')
                    ->label(__('Naam van wie akkoord gaf'))
                    ->required()
                    ->visible(fn ($get) => $get('answer') === 'accepted'),
                Textarea::make('reason')
                    ->label(__('Reden'))
                    ->rows(3)
                    ->required()
                    ->visible(fn ($get) => $get('answer') === 'rejected'),
                Toggle::make('notify_customer')
                    ->label(__('Klant informeren'))
                    ->helperText(__('Stuurt de klant een bevestiging van het akkoord of de afwijzing.'))
                    ->default(true),
            ])
            ->action(function (array $data) use ($quote) {
                if ($data['answer'] === 'accepted') {
                    // De regels staan op wat de beheerder in het formulier heeft
                    // gezet; de klant koos niets op de pagina.
                    $chosen = $quote->lines->where('is_selected', true)->pluck('id')->all();
                    QuoteAcceptance::accept($quote, $chosen, $data['name'], (string) request()->ip(), notifyCustomer: (bool) ($data['notify_customer'] ?? true));
                } else {
                    QuoteAcceptance::reject($quote, $data['reason'], notifyCustomer: (bool) ($data['notify_customer'] ?? true));
                }

                // QuoteAcceptance::accept()/reject() werken op een eigen,
                // vergrendelde lees van de offerte en geven een nieuwe
                // instantie terug; $quote hier (hetzelfde object als
                // EditQuote::$record) blijft zonder deze refresh op de oude
                // status staan, en dan tonen de andere kopacties (deze knop
                // zelf, "Omzetten naar bestelling") pas na een herlaadbeurt
                // de juiste stand.
                $quote->refresh();

                Notification::make()->title(__('Het antwoord is vastgelegd'))->success()->send();
            });
    }
}
