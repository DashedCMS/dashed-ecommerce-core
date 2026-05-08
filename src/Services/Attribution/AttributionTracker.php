<?php

namespace Dashed\DashedEcommerceCore\Services\Attribution;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Centraal punt voor UTM- en attributie-data. Verzamelt touches uit de request,
 * bewaart die in de sessie en kopieert ze naar Cart / Order kolommen.
 */
class AttributionTracker
{
    public const SESSION_KEY = 'dashed_attribution';
    public const SESSION_FIRST_FLAG = 'dashed_attribution_captured_first';

    /**
     * UTM-parameters en click-IDs die rechtstreeks op een eigen kolom landen.
     *
     * @var array<int,string>
     */
    public const TRACKED_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'msclkid',
    ];

    /**
     * Vangt een touch op uit de request en bewaart die in de sessie. Geeft
     * de touch-array terug, of null als er niets te bewaren viel.
     *
     * @return array<string,mixed>|null
     */
    public static function captureFromRequest(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $session = $request->session();

        $params = [];
        foreach (self::TRACKED_PARAMS as $key) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '') {
                $params[$key] = mb_substr($value, 0, 255);
            }
        }

        $hasUtm = ! empty($params);
        $firstCaptured = $session->get(self::SESSION_FIRST_FLAG, false);

        if (! $hasUtm && $firstCaptured) {
            // Niets te doen: geen UTM en eerste-touch is al geregistreerd.
            return null;
        }

        $touch = $params + [
            'landing_page' => mb_substr($request->fullUrl(), 0, 2048),
            'referrer' => self::truncateOrNull($request->headers->get('referer'), 2048),
            'at' => Carbon::now()->toIso8601String(),
        ];

        $existing = $session->get(self::SESSION_KEY, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        if ($hasUtm) {
            if (empty($existing['first_touch'])) {
                $existing['first_touch'] = $touch;
            }
            $existing['last_touch'] = $touch;
        } elseif (! $firstCaptured) {
            // Eerste request van de sessie zonder UTM: minimaal de landingspagina vastleggen.
            if (empty($existing['first_touch'])) {
                $existing['first_touch'] = $touch;
            }
            if (empty($existing['last_touch'])) {
                $existing['last_touch'] = $touch;
            }
        }

        $session->put(self::SESSION_KEY, $existing);
        $session->put(self::SESSION_FIRST_FLAG, true);

        return $touch;
    }

    /**
     * Haalt de huidige first/last touches uit de sessie.
     *
     * @return array{first_touch: array<string,mixed>|null, last_touch: array<string,mixed>|null}
     */
    public static function pullFromSession(): array
    {
        if (! function_exists('session') || ! app()->bound('session.store')) {
            return ['first_touch' => null, 'last_touch' => null];
        }

        try {
            $existing = session()->get(self::SESSION_KEY, []);
        } catch (\Throwable $e) {
            return ['first_touch' => null, 'last_touch' => null];
        }

        if (! is_array($existing)) {
            return ['first_touch' => null, 'last_touch' => null];
        }

        return [
            'first_touch' => $existing['first_touch'] ?? null,
            'last_touch' => $existing['last_touch'] ?? null,
        ];
    }

    /**
     * Wist de attribution-data uit de sessie. Aanroepen na succesvolle order-plaatsing.
     */
    public static function clearSession(): void
    {
        if (! function_exists('session') || ! app()->bound('session.store')) {
            return;
        }

        try {
            session()->forget(self::SESSION_KEY);
            session()->forget(self::SESSION_FIRST_FLAG);
        } catch (\Throwable $e) {
            // Stil falen: tracking mag flow niet breken.
        }
    }

    /**
     * Kopieert de sessie-attributie idempotent naar een Cart. Bestaande
     * waardes worden niet overschreven.
     */
    public static function attachToCart(Cart $cart): void
    {
        if (! Schema::hasColumn($cart->getTable(), 'utm_source')) {
            return;
        }

        $touches = self::pullFromSession();
        self::applyTouches($cart, $touches);
    }

    /**
     * Kopieert attributie naar een Order. Voorkeur: data die al op de Cart
     * staat. Anders valt het terug op de sessie.
     */
    public static function attachToOrder(Order $order, ?Cart $cart = null): void
    {
        if (! Schema::hasColumn($order->getTable(), 'utm_source')) {
            return;
        }

        $cart = $cart ?: ($order->cart_id ? $order->cart : null);

        $touches = self::touchesFromCart($cart);
        if ($touches === null) {
            $touches = self::pullFromSession();
        }

        self::applyTouches($order, $touches);
    }

    /**
     * Bouwt een touches-array op basis van een bestaande Cart. Geeft null
     * terug als de cart geen attributie-data heeft.
     *
     * @return array{first_touch: array<string,mixed>|null, last_touch: array<string,mixed>|null}|null
     */
    protected static function touchesFromCart(?Cart $cart): ?array
    {
        if (! $cart) {
            return null;
        }

        $hasAny = false;
        foreach (self::TRACKED_PARAMS as $key) {
            if (! empty($cart->{$key})) {
                $hasAny = true;

                break;
            }
        }

        if (! $hasAny && empty($cart->landing_page) && empty($cart->landing_page_referrer)) {
            return null;
        }

        $base = [
            'landing_page' => $cart->landing_page,
            'referrer' => $cart->landing_page_referrer,
        ];

        foreach (self::TRACKED_PARAMS as $key) {
            if (! empty($cart->{$key})) {
                $base[$key] = $cart->{$key};
            }
        }

        $first = $base + [
            'at' => optional($cart->attribution_first_touch_at)->toIso8601String(),
        ];
        $last = $base + [
            'at' => optional($cart->attribution_last_touch_at)->toIso8601String(),
        ];

        $extra = $cart->attribution_extra;
        if (is_array($extra) && ! empty($extra)) {
            $first['_extra'] = $extra;
            $last['_extra'] = $extra;
        }

        return [
            'first_touch' => $first,
            'last_touch' => $last,
        ];
    }

    /**
     * @param array{first_touch: array<string,mixed>|null, last_touch: array<string,mixed>|null} $touches
     */
    protected static function applyTouches($model, array $touches): void
    {
        $firstTouch = $touches['first_touch'] ?? null;
        $lastTouch = $touches['last_touch'] ?? null;

        if (! $firstTouch && ! $lastTouch) {
            return;
        }

        $dirty = false;
        $extra = is_array($model->attribution_extra ?? null) ? $model->attribution_extra : [];

        // Last-touch wint voor de gestructureerde kolommen.
        if ($lastTouch) {
            foreach (self::TRACKED_PARAMS as $key) {
                if (empty($model->{$key}) && ! empty($lastTouch[$key])) {
                    $model->{$key} = mb_substr((string) $lastTouch[$key], 0, 255);
                    $dirty = true;
                }
            }

            if (empty($model->landing_page) && ! empty($lastTouch['landing_page'])) {
                $model->landing_page = mb_substr((string) $lastTouch['landing_page'], 0, 2048);
                $dirty = true;
            }
            if (empty($model->landing_page_referrer) && ! empty($lastTouch['referrer'])) {
                $model->landing_page_referrer = mb_substr((string) $lastTouch['referrer'], 0, 2048);
                $dirty = true;
            }

            if (empty($model->attribution_last_touch_at) && ! empty($lastTouch['at'])) {
                $model->attribution_last_touch_at = self::parseDate($lastTouch['at']);
                $dirty = true;
            }
        }

        if ($firstTouch) {
            if (empty($model->attribution_first_touch_at) && ! empty($firstTouch['at'])) {
                $model->attribution_first_touch_at = self::parseDate($firstTouch['at']);
                $dirty = true;
            }
        }

        // Niet-gestructureerde info bewaren we in de extra-json zodat we
        // later zonder migratie nieuwe parameters kunnen opnemen.
        $rawFirst = self::strippedTouch($firstTouch);
        $rawLast = self::strippedTouch($lastTouch);
        if (! empty($rawFirst) || ! empty($rawLast)) {
            $extra['first_touch'] = $rawFirst ?: ($extra['first_touch'] ?? null);
            $extra['last_touch'] = $rawLast ?: ($extra['last_touch'] ?? null);
            $model->attribution_extra = $extra;
            $dirty = true;
        }

        if ($dirty && $model->exists) {
            $model->saveQuietly();
        }
    }

    /**
     * Geeft een touch terug zonder de velden die al een eigen kolom hebben,
     * zodat alleen 'rest'-data in attribution_extra belandt.
     *
     * @return array<string,mixed>
     */
    protected static function strippedTouch(?array $touch): array
    {
        if (! $touch) {
            return [];
        }

        $structured = array_merge(self::TRACKED_PARAMS, ['landing_page', 'referrer', 'at']);

        return array_filter(
            $touch,
            fn ($key) => ! in_array($key, $structured, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected static function parseDate($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function truncateOrNull(?string $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
