<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Throwable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Instellingen van de Meta Conversions API voor één site, in drie leesmodi.
 *
 * for(): zonder cache via Customsetting. Een job in de wachtrij mag geen
 * verouderde toggle of token zien. Die lezing wist wel de hele
 * instellingencache van de site, dus alleen voor zeldzame momenten (een order).
 *
 * cached(): via de gewone instellingencache. Voor webverzoeken die vaak
 * voorkomen (productweergave, toevoegen aan de wagen): die starten schoon en
 * Customsetting::set() leegt de gedeelde cache al.
 *
 * fresh(): één directe databasequery, zonder de cache aan te raken. Voor jobs
 * die vaak draaien: een worker leeft lang en houdt per proces een statische
 * cache bij.
 */
class MetaCapiSettings
{
    public const VALUE_INCL_VAT = 'incl_vat';
    public const VALUE_EXCL_VAT = 'excl_vat';
    public const VALUE_EXCL_VAT_EXCL_SHIPPING = 'excl_vat_excl_shipping';

    public const VALUE_MODES = [
        self::VALUE_INCL_VAT,
        self::VALUE_EXCL_VAT,
        self::VALUE_EXCL_VAT_EXCL_SHIPPING,
    ];

    public const MODE_UNCACHED = 'uncached';
    public const MODE_CACHED = 'cached';
    public const MODE_DIRECT = 'direct';

    public const EVENT_ADD_TO_CART = 'AddToCart';
    public const EVENT_INITIATE_CHECKOUT = 'InitiateCheckout';
    public const EVENT_VIEW_CONTENT = 'ViewContent';

    /** De browser-events met een eigen schakelaar, met de naam van hun instelling. */
    public const BROWSER_EVENTS = [
        self::EVENT_ADD_TO_CART => 'meta_capi_event_add_to_cart',
        self::EVENT_INITIATE_CHECKOUT => 'meta_capi_event_initiate_checkout',
        self::EVENT_VIEW_CONTENT => 'meta_capi_event_view_content',
    ];

    public const KEYS = [
        'meta_capi_enabled',
        'meta_capi_access_token',
        'meta_capi_test_event_code',
        'meta_capi_value_mode',
        'meta_capi_event_add_to_cart',
        'meta_capi_event_initiate_checkout',
        'meta_capi_event_view_content',
        'facebook_pixel_conversion_id',
        'facebook_pixel_site_id',
    ];

    /** @var array<string, mixed>|null */
    protected ?array $loaded = null;

    public function __construct(public readonly string $siteId, protected string $mode = self::MODE_UNCACHED)
    {
    }

    public static function for(?string $siteId): self
    {
        return new self($siteId ?: Sites::getActive());
    }

    /** Via de gewone (gecachete) instellingen: voor veelvoorkomende webverzoeken. */
    public static function cached(?string $siteId): self
    {
        return new self($siteId ?: Sites::getActive(), self::MODE_CACHED);
    }

    /** Eén directe databasequery, buiten de cache om: voor jobs die vaak draaien. */
    public static function fresh(?string $siteId): self
    {
        return new self($siteId ?: Sites::getActive(), self::MODE_DIRECT);
    }

    /**
     * Staat dit browser-event (AddToCart, InitiateCheckout, ViewContent) aan?
     * Nooit ingesteld telt als aan: de schakelaars staan standaard aan zodra
     * de Conversions API zelf aan staat.
     */
    public function eventEnabled(string $eventName): bool
    {
        $key = self::BROWSER_EVENTS[$eventName] ?? null;
        if ($key === null) {
            return false;
        }

        $value = $this->get($key);

        return $value === null || $value === '' || in_array($value, [true, 1, '1', 'true'], true);
    }

    /** Klaar om te versturen én dit event staat aan. */
    public function readyFor(string $eventName): bool
    {
        return $this->ready() && $this->eventEnabled($eventName);
    }

    public function enabled(): bool
    {
        return in_array($this->get('meta_capi_enabled'), [true, 1, '1', 'true'], true);
    }

    public function pixelId(): ?string
    {
        $id = trim((string) ($this->get('facebook_pixel_conversion_id') ?: $this->get('facebook_pixel_site_id')));

        return $id === '' ? null : $id;
    }

    public function accessToken(): ?string
    {
        $stored = (string) $this->get('meta_capi_access_token');
        if ($stored === '') {
            return null;
        }

        try {
            $token = Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }

        return $token === '' ? null : $token;
    }

    public function hasAccessToken(): bool
    {
        return $this->accessToken() !== null;
    }

    /** Leeg of null laat het opgeslagen token ongemoeid. */
    public function storeAccessToken(?string $token): void
    {
        $token = trim((string) $token);
        if ($token === '') {
            return;
        }

        Customsetting::set('meta_capi_access_token', Crypt::encryptString($token), $this->siteId);
    }

    public function testEventCode(): ?string
    {
        $code = trim((string) $this->get('meta_capi_test_event_code'));

        return $code === '' ? null : $code;
    }

    public function valueMode(): string
    {
        $mode = (string) $this->get('meta_capi_value_mode');

        return in_array($mode, self::VALUE_MODES, true) ? $mode : self::VALUE_INCL_VAT;
    }

    public function graphVersion(): string
    {
        return (string) config('services.meta.graph_version', 'v26.0');
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->pixelId() !== null && $this->hasAccessToken();
    }

    protected function get(string $name): mixed
    {
        return match ($this->mode) {
            self::MODE_CACHED => Customsetting::get($name, $this->siteId),
            self::MODE_DIRECT => $this->loadDirect()[$name] ?? null,
            default => Customsetting::get($name, $this->siteId, null, null, 'default', true),
        };
    }

    /** @return array<string, mixed> */
    protected function loadDirect(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        // Dezelfde keuze van kolom als Customsetting zelf: json gaat voor value.
        $loaded = [];
        $rows = Customsetting::query()
            ->where('site_id', $this->siteId)
            ->whereNull('locale')
            ->whereIn('name', self::KEYS)
            ->get();

        foreach ($rows as $row) {
            if ($row->json !== null) {
                $loaded[$row->name] = $row->json;
            } elseif ($row->value !== null) {
                $loaded[$row->name] = $row->value === '[]' ? [] : $row->value;
            }
        }

        return $this->loaded = $loaded;
    }
}
