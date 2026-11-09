<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\MetaCapiEvent;
use Dashed\DashedEcommerceCore\Models\MetaCapiEventCount;
use Dashed\DashedEcommerceCore\Services\Meta\ConversionsApi;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

/**
 * Stuurt één kant-en-klaar browser-event (ViewContent, AddToCart,
 * InitiateCheckout) naar Meta. Het event is bij het klaarzetten al opgebouwd
 * uit het verzoek van de bezoeker. Een geslaagd event krijgt geen logregel,
 * alleen een dagteller: het zijn er te veel om te lezen. Een mislukt event
 * komt in het log, hoogstens één regel per uur per site, event en status.
 *
 * Deze events delen de wachtrij met facturen en voorraad en mogen die niet
 * ophouden: één poging per event, de job gooit niet bij een mislukte
 * verzending, en na een storing bij Meta slaat hij twee minuten alles over.
 */
class SendMetaEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Zo lang na een storing of time-out gaat er niets naar Meta (seconden). */
    public const DOWN_SECONDS = 120;

    public int $tries = 1;

    public int $timeout = 60;

    /** @param  array<string, mixed>  $event */
    public function __construct(public string $siteId, public array $event)
    {
    }

    public static function downKey(string $siteId): string
    {
        return "meta-capi-down:{$siteId}";
    }

    public function handle(ConversionsApi $api): void
    {
        $eventId = $this->event['event_id'] ?? null;
        $eventName = (string) ($this->event['event_name'] ?? '');
        if (! is_string($eventId) || $eventId === '' || $eventName === '') {
            return;
        }

        // Zet de functie na het klaarzetten uit, dan hoeft er niets meer weg.
        $settings = MetaCapiSettings::fresh($this->siteId);
        if (! $settings->readyFor($eventName)) {
            return;
        }

        // Meta gaf net een storing: niet elk wachtend event opnieuw een halve minuut laten hangen.
        if (Cache::has(static::downKey($this->siteId))) {
            $this->count($eventName, MetaCapiEventCount::FAILED);

            return;
        }

        $result = $api->sendEvent($this->siteId, $this->event, $settings);

        if ($result['ok']) {
            $this->count($eventName, MetaCapiEventCount::SENT);

            return;
        }

        $this->count($eventName, MetaCapiEventCount::FAILED);

        // Een storing of time-out (5xx, geen verbinding) kan vanzelf overgaan; een 4xx zegt niets over Meta's bereikbaarheid.
        if ($result['status'] === 0 || $result['status'] >= 500) {
            Cache::put(static::downKey($this->siteId), 1, self::DOWN_SECONDS);
        }

        // Dezelfde fout komt bij elk volgend event terug (ingetrokken token,
        // storing): hoogstens één regel per uur per site, event en status.
        if (! Cache::add("meta-capi-fail:{$this->siteId}:{$eventName}:{$result['status']}", 1, 3600)) {
            return;
        }

        MetaCapiEvent::updateOrCreate(['event_id' => $eventId], [
            'site_id' => $this->siteId,
            'event_name' => $eventName,
            'payload' => $this->storablePayload(),
            'status' => MetaCapiEvent::STATUS_FAILED,
            'attempts' => 1,
            'response' => ['status' => $result['status'], 'body' => $result['body']],
        ]);
    }

    /** Een teller mag het versturen nooit laten mislukken. */
    protected function count(string $eventName, string $column): void
    {
        try {
            MetaCapiEventCount::record($this->siteId, $eventName, $column);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Het opgeslagen event bevat geen IP-adres of user agent van de bezoeker. */
    protected function storablePayload(): array
    {
        $payload = $this->event;
        unset($payload['user_data']['client_ip_address'], $payload['user_data']['client_user_agent']);

        return $payload;
    }
}
