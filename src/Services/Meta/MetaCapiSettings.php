<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Throwable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Instellingen van de Meta Conversions API voor één site. Leest altijd zonder
 * cache: een job in de wachtrij mag geen verouderde toggle of token zien.
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

    public function __construct(public readonly string $siteId)
    {
    }

    public static function for(?string $siteId): self
    {
        return new self($siteId ?: Sites::getActive());
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
        return Customsetting::get($name, $this->siteId, null, null, 'default', true);
    }
}
