<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\Cart;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Jobs\SendMetaEventJob;

/**
 * AddToCart, InitiateCheckout en ViewContent voor de Conversions API. De
 * browserpixel en de server melden hetzelfde event met hetzelfde event-ID,
 * zodat Meta de twee ontdubbelt. Het event wordt opgebouwd uit het verzoek van
 * de bezoeker en gaat altijd via de wachtrij naar Meta. De wagen, de checkout
 * en de productpagina wachten nooit op Meta en breken er nooit door: bij
 * twijfel een lege uitkomst, en dan stuurt de pixel wat hij altijd stuurde.
 *
 * Waar het event-ID vandaan komt:
 * - AddToCart en InitiateCheckout: de server maakt het in een Livewire-verzoek
 *   (nooit uit een paginacache) en geeft het in de dispatch-payload mee.
 * - ViewContent: de browser maakt het zelf en meldt het aan een eigen endpoint.
 *   Een productpagina kan uit de paginacache komen; een event-ID in die HTML
 *   zou voor elke bezoeker hetzelfde zijn.
 */
class MetaBrowserEvents
{
    /** Rem tegen bots en misbruik: zoveel server-events per minuut per IP-adres. */
    public const RATE_LIMIT_PER_MINUTE = 90;

    /** Eén ViewContent per bezoeker per product per zoveel minuten. */
    public const VIEW_CONTENT_DEDUPE_MINUTES = 30;

    /** Dezelfde wagen opnieuw openen in de checkout binnen deze tijd is hetzelfde event. */
    public const CHECKOUT_DEDUPE_MINUTES = 30;

    public const VIEW_CONTENT_ID_PATTERN = '/^vc_[A-Za-z0-9\-]{16,64}$/';

    /**
     * De sleutel van de rem. Het IP-adres gaat er gehasht in: een cache in de
     * database ruimt verlopen rijen niet op en zou anders adressen van
     * bezoekers leesbaar bewaren.
     */
    public static function rateKey(?string $ip): string
    {
        return 'meta-capi-browser:' . sha1($ip ?? 'onbekend');
    }

    /**
     * Voor de payload van `productAddedToCart`: het event-ID en de waarde die
     * de pixel moet gebruiken. Leeg als er geen server-event gaat. Het
     * event-ID staat achteraan: wie dit in een payload voegt, houdt het product
     * vooraan (zie AddToCartPayload).
     *
     * @param  Product|int|string  $product  het product of zijn id
     * @param  string|null  $name  alleen nodig bij een los id; anders komt de naam van het product
     * @return array{metaValue?: string, metaEventId?: string}
     */
    public function addToCart(Product|int|string $product, int $quantity, float $unitPrice, ?string $name = null, ?string $category = null): array
    {
        try {
            $settings = $this->settingsFor(MetaCapiSettings::EVENT_ADD_TO_CART);
            if (! $settings) {
                return [];
            }

            if (! $product instanceof Product) {
                $product = Product::find($product);
                if (! $product) {
                    return [];
                }
            }

            $quantity = max(1, $quantity);
            // Eerst afronden: de browser rekent met number_format($prijs, 2) en moet hetzelfde bedrag krijgen.
            $unitPrice = round($unitPrice, 2);
            $id = (string) $product->id;
            $value = round(MetaEventValue::unit($unitPrice, $product, $settings->valueMode()) * $quantity, 2);
            $eventId = 'atc_' . Str::uuid();

            $queued = $this->queue($settings, MetaCapiSettings::EVENT_ADD_TO_CART, $eventId, array_filter([
                'currency' => 'EUR',
                'value' => $value,
                'content_type' => 'product',
                'content_ids' => [$id],
                'contents' => [['id' => $id, 'quantity' => $quantity, 'item_price' => $unitPrice]],
                'num_items' => $quantity,
                'content_name' => $this->plainName($name ?? $product->name),
                'content_category' => $this->plainName($category ?? $product->productCategories->first()?->name),
            ], fn ($value) => $value !== null && $value !== ''), $this->cartEmail());

            return $queued ? ['metaValue' => number_format($value, 2, '.', ''), 'metaEventId' => $eventId] : [];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Voor de payload van `checkoutInitiated`. Dezelfde wagen met dezelfde
     * inhoud opnieuw openen (herladen, terug uit de betaalpagina) geeft
     * hetzelfde event-ID terug zonder een tweede server-event.
     *
     * @param  array<int, mixed>  $items  regels zoals Checkout::mount() ze opbouwt: item_id, quantity, price
     * @return array{metaValue?: string, metaEventId?: string}
     */
    public function initiateCheckout(array $items, float $cartTotal, ?Cart $cart = null, float $tax = 0, float $shippingCosts = 0): array
    {
        try {
            $settings = $this->settingsFor(MetaCapiSettings::EVENT_INITIATE_CHECKOUT);
            if (! $settings) {
                return [];
            }

            $contents = [];
            foreach ($items as $item) {
                if (! is_array($item) || empty($item['item_id'])) {
                    continue;
                }

                $contents[] = [
                    'id' => (string) $item['item_id'],
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                    'item_price' => round((float) (is_numeric($item['price'] ?? null) ? $item['price'] : 0), 2),
                ];
            }

            // Een lege wagen is geen checkout: zo opent alleen een crawler de pagina.
            if ($contents === []) {
                return [];
            }

            $value = MetaEventValue::cart($cartTotal, $tax, $shippingCosts, $settings->valueMode());
            $result = fn (string $eventId) => ['metaValue' => number_format($value, 2, '.', ''), 'metaEventId' => $eventId];

            $dedupeKey = $cart?->id
                ? 'meta-capi-checkout:' . $cart->id . ':' . sha1(json_encode([$contents, $value]))
                : null;

            if ($dedupeKey && is_string($known = Cache::get($dedupeKey))) {
                return $result($known);
            }

            $eventId = 'ic_' . Str::uuid();

            $queued = $this->queue($settings, MetaCapiSettings::EVENT_INITIATE_CHECKOUT, $eventId, [
                'currency' => 'EUR',
                'value' => $value,
                'content_type' => 'product',
                'content_ids' => array_column($contents, 'id'),
                'contents' => $contents,
                'num_items' => array_sum(array_column($contents, 'quantity')),
            ], $cart?->abandoned_email);

            if (! $queued) {
                return [];
            }

            if ($dedupeKey) {
                Cache::put($dedupeKey, $eventId, self::CHECKOUT_DEDUPE_MINUTES * 60);
            }

            return $result($eventId);
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Voor de payload van `viewProduct`: alleen de waarde volgens de value
     * mode. Geen event-ID: dat maakt de browser zelf (paginacache).
     *
     * @return array{metaValue?: string}
     */
    public function viewContentPayload(Product $product, float $unitPrice): array
    {
        try {
            $settings = MetaCapiSettings::cached(Sites::getActive());
            if (! $settings->enabled() || ! $settings->readyFor(MetaCapiSettings::EVENT_VIEW_CONTENT)) {
                return [];
            }

            return ['metaValue' => number_format(MetaEventValue::unit(round($unitPrice, 2), $product, $settings->valueMode()), 2, '.', '')];
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * De browser meldt dat hij een productpagina toonde, met het event-ID dat
     * hij ook aan de pixel gaf. Prijs en naam komen van de server, nooit uit
     * het verzoek. True als er een event in de wachtrij staat.
     */
    public function viewContent(Request $request, int $productId, string $eventId): bool
    {
        try {
            if ($productId < 1 || ! preg_match(self::VIEW_CONTENT_ID_PATTERN, $eventId)) {
                return false;
            }

            $settings = $this->settingsFor(MetaCapiSettings::EVENT_VIEW_CONTENT, $request);
            if (! $settings) {
                return false;
            }

            // Ontdubbelen vóór de databasequery: herladen en heen en weer klikken kost dan niets.
            $visitor = RequestSignals::fbp() ?? (RequestSignals::ip($request) . '|' . RequestSignals::userAgent($request));
            $dedupeKey = 'meta-capi-view:' . $settings->siteId . ':' . $productId . ':' . sha1($visitor);
            if (! Cache::add($dedupeKey, 1, self::VIEW_CONTENT_DEDUPE_MINUTES * 60)) {
                return false;
            }

            $product = Product::publicShowable()->find($productId);
            if (! $product) {
                return false;
            }

            $id = (string) $product->id;
            $unitPrice = round((float) $product->priceForUser(), 2);

            return $this->queue($settings, MetaCapiSettings::EVENT_VIEW_CONTENT, $eventId, array_filter([
                'currency' => 'EUR',
                'value' => MetaEventValue::unit($unitPrice, $product, $settings->valueMode()),
                'content_type' => 'product',
                'content_ids' => [$id],
                'contents' => [['id' => $id, 'quantity' => 1, 'item_price' => $unitPrice]],
                'content_name' => $this->plainName($product->name),
                'content_category' => $this->plainName($product->productCategories->first()?->name),
            ], fn ($value) => $value !== null && $value !== ''), $this->cartEmailWithoutCreatingCart($request), $request);
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * De instellingen als dit event voor dit verzoek mag: functie aan, geen
     * bot, toestemming en binnen de rem. Anders null. Goedkoopste uitgang
     * eerst: met de functie uit gebeurt er verder niets.
     */
    protected function settingsFor(string $eventName, ?Request $request = null): ?MetaCapiSettings
    {
        $settings = MetaCapiSettings::cached(Sites::getActive());
        if (! $settings->enabled() || ! $settings->readyFor($eventName)) {
            return null;
        }

        $request ??= request();

        // Meta eist een user agent bij action_source website; een bot is geen klant.
        if (RequestSignals::isBot($request) || ! MarketingConsent::granted($request, $settings->siteId)) {
            return null;
        }

        $rateKey = static::rateKey($request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, self::RATE_LIMIT_PER_MINUTE)) {
            return null;
        }
        RateLimiter::hit($rateKey, 60);

        return $settings;
    }

    /** @param  array<string, mixed>  $customData */
    protected function queue(MetaCapiSettings $settings, string $eventName, string $eventId, array $customData, ?string $email = null, ?Request $request = null): bool
    {
        $request ??= request();

        $event = array_filter([
            'event_name' => $eventName,
            'event_time' => Carbon::now()->timestamp,
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => RequestSignals::pageUrl($request),
            'user_data' => $this->userData($request, $email),
            'custom_data' => $customData,
        ], fn ($value) => $value !== null && $value !== []);

        SendMetaEventJob::dispatch($settings->siteId, $event)->onQueue('ecommerce');

        return true;
    }

    /**
     * Gehashte gegevens alleen als ze bekend zijn: een ingelogde klant, of het
     * e-mailadres dat de bezoeker al in de checkout invulde.
     *
     * @return array<string, mixed>
     */
    protected function userData(Request $request, ?string $cartEmail = null): array
    {
        $user = auth()->user();
        $email = $user?->email ?: $cartEmail;

        $hashed = array_filter([
            'em' => MetaHasher::email($email),
            // Zelfde keuze als bij de Purchase: het klant-ID, anders het e-mailadres.
            'external_id' => $user ? MetaHasher::externalId($user->getAuthIdentifier()) : MetaHasher::email($email),
        ]);

        $plain = array_filter([
            'client_ip_address' => RequestSignals::ip($request),
            'client_user_agent' => RequestSignals::userAgent($request),
            'fbp' => RequestSignals::fbp(),
            'fbc' => RequestSignals::fbc($request),
        ]);

        return array_map(fn (string $hash) => [$hash], $hashed) + $plain;
    }

    /** De wagen bestaat hier al (er is net iets in gelegd). */
    protected function cartEmail(): ?string
    {
        return rescue(fn () => cartHelper()->getCart()->abandoned_email, null, false) ?: null;
    }

    /** Een productweergave mag geen wagen aanmaken: alleen opzoeken met de bestaande cookie. */
    protected function cartEmailWithoutCreatingCart(Request $request): ?string
    {
        if (auth()->check()) {
            return null;
        }

        $token = $request->cookie(config('dashed-ecommerce.cart_cookie', 'cart_token'));
        if (! is_string($token) || ! Str::isUuid($token)) {
            return null;
        }

        return Cart::where('token', $token)->value('abandoned_email') ?: null;
    }

    protected function plainName(mixed $name): ?string
    {
        if (is_array($name)) {
            $name = reset($name);
        }

        return is_string($name) ? RequestSignals::text($name, 200) : null;
    }
}
