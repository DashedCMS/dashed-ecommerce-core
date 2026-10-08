<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Throwable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Dashed\DashedEcommerceCore\Models\Order;
use Illuminate\Http\Client\ConnectionException;

/**
 * Bouwt events voor Meta's Conversions API en verstuurt ze. Het toegangstoken
 * gaat in de body van het verzoek en komt nooit in het resultaat, zodat het
 * niet in logs of in de opgeslagen response belandt.
 */
class ConversionsApi
{
    /** Meta weigert events ouder dan zeven dagen; een dag marge. */
    protected const MAX_EVENT_AGE_DAYS = 6;

    public static function eventId(Order $order): string
    {
        return 'purchase_' . $order->id;
    }

    /** @return array<string,mixed> */
    public function purchaseEvent(Order $order): array
    {
        $settings = MetaCapiSettings::for($order->site_id);

        return array_filter([
            'event_name' => 'Purchase',
            'event_time' => $this->eventTime($order),
            'event_id' => static::eventId($order),
            'action_source' => 'website',
            'event_source_url' => $order->tracking?->event_source_url,
            'user_data' => $this->buildUserData($order),
            'custom_data' => $this->buildCustomData($order, $settings->valueMode()),
        ], fn ($value) => $value !== null && $value !== []);
    }

    /** @return array<string,mixed> */
    public function buildUserData(Order $order): array
    {
        $tracking = $order->tracking;
        $countryCode = $order->countryCode;

        $hashed = array_filter([
            'em' => MetaHasher::email($order->email),
            'ph' => MetaHasher::phone($order->phone_number, $countryCode),
            'fn' => MetaHasher::name($order->first_name),
            'ln' => MetaHasher::name($order->last_name),
            'ct' => MetaHasher::city($order->city),
            'zp' => MetaHasher::zip($order->zip_code),
            'country' => MetaHasher::country($countryCode),
            'external_id' => $order->user_id
                ? MetaHasher::externalId($order->user_id)
                : MetaHasher::email($order->email),
        ]);

        $plain = array_filter([
            'client_ip_address' => $this->validIp($order->ip),
            'client_user_agent' => $tracking?->client_user_agent,
            'fbp' => $tracking?->meta_fbp,
            'fbc' => $tracking?->meta_fbc,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        return array_map(fn (string $hash) => [$hash], $hashed) + $plain;
    }

    /** @return array<string,mixed> */
    public function buildCustomData(Order $order, string $valueMode): array
    {
        $contents = MetaPurchaseValue::contents($order);

        return [
            'currency' => 'EUR',
            'value' => MetaPurchaseValue::for($order, $valueMode),
            'content_type' => 'product',
            'content_ids' => array_column($contents, 'id'),
            'contents' => $contents,
            'num_items' => array_sum(array_column($contents, 'quantity')),
            'order_id' => (string) $order->id,
        ];
    }

    /**
     * @param  array<string,mixed>  $event
     * @return array{ok: bool, status: int, body: array<string,mixed>}
     */
    public function sendEvent(string $siteId, array $event): array
    {
        $settings = MetaCapiSettings::for($siteId);
        $pixelId = $settings->pixelId();
        $token = $settings->accessToken();

        if ($pixelId === null || $token === null) {
            return ['ok' => false, 'status' => 0, 'body' => ['error' => 'Pixel-ID of toegangstoken ontbreekt']];
        }

        $body = ['data' => [$event], 'access_token' => $token];
        if ($testEventCode = $settings->testEventCode()) {
            $body['test_event_code'] = $testEventCode;
        }

        try {
            // Alleen opnieuw proberen bij een storing of een verbindingsfout:
            // een 4xx (ongeldig token, afgekeurd event) wordt niet beter.
            $response = Http::retry(3, 500, function (Throwable $e) {
                return $e instanceof ConnectionException
                    || ($e instanceof \Illuminate\Http\Client\RequestException && $e->response->serverError());
            }, throw: false)
                ->timeout(10)
                ->asJson()
                ->acceptJson()
                ->post("https://graph.facebook.com/{$settings->graphVersion()}/{$pixelId}/events", $body);
        } catch (Throwable $e) {
            return ['ok' => false, 'status' => 0, 'body' => ['error' => str_replace($token, '***', $e->getMessage())]];
        }

        $decoded = $response->json();

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'body' => is_array($decoded) ? $decoded : ['raw' => substr((string) $response->body(), 0, 500)],
        ];
    }

    protected function eventTime(Order $order): int
    {
        $paidAt = $order->orderPayments
            ->where('status', 'paid')
            ->sortByDesc('created_at')
            ->first()?->created_at;

        $now = Carbon::now();
        if (! $paidAt || $paidAt->gt($now) || $paidAt->lt($now->copy()->subDays(self::MAX_EVENT_AGE_DAYS))) {
            return $now->timestamp;
        }

        return $paidAt->timestamp;
    }

    protected function validIp(?string $ip): ?string
    {
        $ip = trim((string) $ip);

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }
}
