<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderTracking;
use Dashed\DashedEcommerceCore\Http\Middleware\CaptureMetaClickId;

/**
 * Legt bij het aanmaken van een checkout-order vast wat er bij de
 * betaal-webhook niet meer is: de Meta-cookies, de user agent, de
 * checkout-URL en de toestemming van dat moment.
 */
class OrderTrackingRecorder
{
    /**
     * @param  array<string,mixed>|null  $cookies  standaard $_COOKIE: Laravel
     *   gooit door JavaScript gezette (onversleutelde) cookies weg, dus
     *   $request->cookie() geeft hier null.
     */
    public function record(Order $order, Request $request, ?array $cookies = null): ?OrderTracking
    {
        try {
            $cookies ??= $_COOKIE;

            return OrderTracking::firstOrCreate(['order_id' => $order->id], [
                'meta_fbp' => $this->metaCookie($cookies['_fbp'] ?? null, 255),
                'meta_fbc' => $this->metaCookie($cookies['_fbc'] ?? null, 1000) ?? $this->fallbackFbc($order, $request),
                'client_user_agent' => $this->truncate($request->userAgent(), 1000),
                'event_source_url' => $this->truncate($request->headers->get('referer'), 2048) ?? $this->checkoutUrl(),
                'marketing_consent' => MarketingConsent::granted($request, $order->site_id),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Alleen waarden in Meta's eigen formaat (fb.{n}.{ms}.{rest}) en binnen de kolomlengte. */
    protected function metaCookie(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength || ! preg_match('/^fb\.\d+\.\d+\.[A-Za-z0-9_\-.]+$/', $value)) {
            return null;
        }

        return $value;
    }

    protected function fallbackFbc(Order $order, Request $request): ?string
    {
        $fromSession = $request->hasSession() ? $request->session()->get(CaptureMetaClickId::SESSION_KEY) : null;
        if ($fbc = $this->metaCookie($fromSession, 1000)) {
            return $fbc;
        }

        $fbclid = trim((string) $order->fbclid);
        if ($fbclid === '') {
            return null;
        }

        $clickedAt = $order->attribution_last_touch_at ? Carbon::parse($order->attribution_last_touch_at) : Carbon::now();

        return $this->metaCookie(CaptureMetaClickId::fbcFromClickId($fbclid, $clickedAt->getTimestampMs()), 1000);
    }

    protected function truncate(?string $value, int $length): ?string
    {
        // Ongeldige bytes eruit (mb_scrub), en nooit midden in een teken knippen (mb_strcut).
        $value = trim(mb_scrub((string) $value, 'UTF-8'));

        return $value === '' ? null : mb_strcut($value, 0, $length, 'UTF-8');
    }

    /** Terugval zonder Referer: de checkoutpagina, nooit het Livewire-endpoint. */
    protected function checkoutUrl(): ?string
    {
        try {
            $url = ShoppingCart::getCheckoutUrl();

            if (! is_string($url) || $url === '' || $url === '#') {
                return null;
            }

            if (str_starts_with($url, '/')) {
                $url = url($url);
            }

            return str_starts_with($url, 'http') ? $this->truncate($url, 2048) : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}
