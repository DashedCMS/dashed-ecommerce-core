<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Validation\ValidationException;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedCore\Classes\AdminActionMonitor;

/**
 * Pincode (MANUAL_PAID_PIN) die verplicht is om een order handmatig op
 * betaald te zetten. Los van wachtwoord en MFA, zodat een gekaapte
 * paneelsessie er niets mee kan: op 31 augustus 2026 werden zo 173
 * cadeaukaarten uitgeleverd op orders van een cent.
 *
 * De pincode zit op de drie ingangen waar een mens een betaling verzint:
 * "Voeg betaling toe" en "Registreer handmatige betaling" in het CMS, en
 * "Markeer als betaald" in de app. Bewust niet als Order::saving-haak: de
 * kassa, de betaalwebhooks en de proforma-flow zetten orders legitiem op
 * betaald vanuit een paneelsessie, en die zouden allemaal breken.
 *
 * Zonder pincode in .env is de controle uit; de Beveiligingscheck meldt
 * dat. Na twee foute pogingen in een kwartier gaat er een melding uit, na
 * vijf is ook de juiste pincode een kwartier geblokkeerd, en elke foute
 * poging staat als notitie bij de bestelling.
 */
class ManualPaymentPin
{
    public const ALERT_AFTER_FAILED_ATTEMPTS = 2;

    public const MAX_FAILED_ATTEMPTS = 5;

    public const WINDOW_MINUTES = 15;

    public static function configured(): bool
    {
        return trim((string) config('dashed-ecommerce-core.security.manual_paid_pin')) !== '';
    }

    public static function required(): bool
    {
        return self::configured();
    }

    /** Het pinveld voor een Filament-actie; alleen zichtbaar en verplicht als de pincode is gezet. */
    public static function formField(): TextInput
    {
        return TextInput::make('pin')
            ->label(__('Pincode'))
            ->helperText(__('De pincode voor handmatig op betaald zetten (MANUAL_PAID_PIN in .env).'))
            ->password()
            ->autocomplete('one-time-code')
            ->visible(fn (): bool => self::required())
            ->required(fn (): bool => self::required());
    }

    /**
     * Als attempt(), maar gooit bij mislukking een validatiefout op het
     * pinveld van de gemounte Filament-actie.
     */
    public static function verifyOrFail(?string $pin, ?Order $order = null): void
    {
        if ($error = self::attempt($pin, $order)) {
            throw ValidationException::withMessages(['mountedActions.0.data.pin' => $error]);
        }
    }

    /**
     * Controleert de pincode. Geeft bij mislukking de foutmelding terug,
     * anders null.
     */
    public static function attempt(?string $pin, ?Order $order = null): ?string
    {
        if (! self::required()) {
            return null;
        }

        $key = self::throttleKey();

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
            return __('Te veel foute pogingen; probeer het over :minuten minuten opnieuw.', ['minuten' => (int) ceil(RateLimiter::availableIn($key) / 60)]);
        }

        if ($pin !== null && $pin !== '' && hash_equals((string) config('dashed-ecommerce-core.security.manual_paid_pin'), $pin)) {
            RateLimiter::clear($key);

            return null;
        }

        $failed = RateLimiter::hit($key, self::WINDOW_MINUTES * 60);

        if ($order) {
            rescue(fn () => OrderLog::createLog(orderId: $order->id, tag: 'order.manual-payment.wrong-pin', note: __('Onjuiste pincode bij handmatig op betaald zetten (poging :poging)', ['poging' => $failed])), report: false);
        }

        if ($failed >= self::ALERT_AFTER_FAILED_ATTEMPTS) {
            AdminActionMonitor::alert(__('Foute pincode bij handmatig op betaald zetten'), [
                __('Bestelling') => $order ? '#' . $order->id . ' (' . $order->email . ')' : __('onbekend'),
                __('Foute pogingen') => $failed . ' ' . __('in :minuten minuten', ['minuten' => self::WINDOW_MINUTES]),
                __('Geblokkeerd') => $failed >= self::MAX_FAILED_ATTEMPTS ? __('ja') : __('nee'),
            ]);
        }

        return __('Onjuiste pincode.');
    }

    protected static function throttleKey(): string
    {
        return 'dashed.manual-paid-pin:' . (auth()->id() ?? request()->ip());
    }
}
