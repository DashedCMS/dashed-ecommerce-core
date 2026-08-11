<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Newsletter;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedNewsletter\Models\NewsletterList;
use Dashed\DashedEcommerceCore\Contracts\SupportsEmailBackfill;

/**
 * Zet de klant van een bestelling op een CMS-nieuwsbrieflijst.
 *
 * Staat in dit pakket en niet in dashed-newsletter, want hier woont Order. De
 * nieuwsbriefmodule hoort niets van bestellingen te weten, net zomin als van
 * Laposta. Registratie gebeurt achter een guard, zodat een webshop zonder
 * nieuwsbriefmodule hier geen last van heeft.
 */
class NewsletterOrderAPI implements SupportsEmailBackfill
{
    /**
     * @param array<string, mixed> $api
     */
    public static function dispatch(Order $order, $api): void
    {
        if (! app()->bound('newsletter')) {
            return;
        }

        // Bol-bestellingen gaan nooit naar marketing: die klant is klant van
        // Bol en heeft hier geen toestemming voor gegeven. Zelfde uitsluiting
        // als in QueueOrderFlowEmailsListener.
        if ((string) ($order->order_origin ?? '') === 'Bol') {
            return;
        }

        $list = NewsletterList::find($api['newsletter_list_id'] ?? null);

        if (! $list) {
            OrderLog::createLog($order->id, note: 'Nieuwsbrief: de ingestelde lijst bestaat niet meer.');

            return;
        }

        $email = (string) ($order->{$api['email_field_id'] ?? ''} ?? '');

        if (! $email) {
            return;
        }

        $fields = [];

        foreach ($api['customFields'] ?? [] as $customField) {
            $value = $order->{$customField['field_id'] ?? ''} ?? null;

            if (filled($value) && filled($customField['newsletter_field_key'] ?? null)) {
                $fields[$customField['newsletter_field_key']] = $value;
            }
        }

        try {
            app('newsletter')->subscribe(
                email: $email,
                list: $list,
                fields: $fields,
                source: 'bestelling',
                consentText: $api['consent_text'] ?? null,
                ip: $order->ip,
            );
        } catch (\Throwable $e) {
            OrderLog::createLog($order->id, note: 'Nieuwsbrief: toevoegen aan ' . $list->name . ' mislukt: ' . $e->getMessage());

            return;
        }

        OrderLog::createLog($order->id, note: 'Nieuwsbrief: toegevoegd aan ' . $list->name . '.');
    }

    public static function formFields(): array
    {
        return [
            Select::make('newsletter_list_id')
                ->label(__('Nieuwsbrieflijst'))
                ->required()
                ->options(fn (): array => NewsletterList::pluck('name', 'id')->all()),
            Select::make('email_field_id')
                ->label(__('Email veld'))
                ->required()
                ->columnSpanFull()
                ->options(Order::getMarketingFields()),
            Repeater::make('customFields')
                ->label(__('Gekoppelde velden'))
                ->schema([
                    Select::make('field_id')
                        ->label(__('Veld uit de bestelling'))
                        ->options(Order::getMarketingFields()),
                    TextInput::make('newsletter_field_key')
                        ->label(__('Sleutel van het nieuwsbriefveld'))
                        ->required(),
                ])
                ->columnSpanFull(),
            Textarea::make('consent_text')
                ->label(__('Toestemmingstekst'))
                ->helperText(__('De tekst die de klant bij het afrekenen te zien kreeg. Deze wordt letterlijk bewaard als bewijs.'))
                ->rows(2)
                ->required(),
        ];
    }

    /**
     * Het backfill-pad, dat losse adressen aanbiedt in plaats van een bestelling.
     *
     * Hier is geen veldkoppeling beschikbaar, dus vallen voornaam en achternaam
     * terug op de sleutels die de standaardvelden van een lijst gebruiken. Heeft
     * de lijst die velden niet, dan worden ze overgeslagen zoals overal.
     *
     * @param array<string, mixed> $api
     * @return array<string, mixed>
     */
    public static function syncEmail(string $email, ?string $firstName, ?string $lastName, array $api): array
    {
        if (! app()->bound('newsletter')) {
            return ['status' => 'skipped', 'error' => 'Nieuwsbriefmodule niet geinstalleerd'];
        }

        $list = NewsletterList::find($api['newsletter_list_id'] ?? null);

        if (! $list) {
            return ['status' => 'skipped', 'error' => 'Geen geldige nieuwsbrieflijst geconfigureerd'];
        }

        $fields = array_filter([
            'voornaam' => $firstName,
            'achternaam' => $lastName,
        ], fn ($value) => filled($value));

        try {
            app('newsletter')->subscribe(
                email: $email,
                list: $list,
                fields: $fields,
                source: 'bestelling',
                consentText: $api['consent_text'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return ['status' => 'skipped', 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }

        return ['status' => 'success', 'error' => null];
    }
}
