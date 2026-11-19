<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Meta\Audiences;

use Throwable;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

/**
 * Instellingen voor het doorzetten van doelgroepen naar Meta, per site.
 *
 * Het token valt terug op dat van de Conversions API. Dat werkt alleen als
 * dat token ook de scope `ads_management` heeft (een systeemgebruiker in
 * Business Manager); een token uit Events Manager heeft die meestal niet,
 * en daarom is er een eigen tokenveld.
 */
class MetaAudienceSettings
{
    public function __construct(public readonly string $siteId)
    {
    }

    public static function for(?string $siteId): self
    {
        return new self($siteId ?: Sites::getActive());
    }

    public function enabled(): bool
    {
        return in_array($this->get('meta_audiences_enabled'), [true, 1, '1', 'true'], true);
    }

    /** Het advertentieaccount zonder `act_`-voorvoegsel, of null. */
    public function adAccountId(): ?string
    {
        $id = preg_replace('/\D+/', '', (string) $this->get('meta_audiences_ad_account_id')) ?? '';

        return $id === '' ? null : $id;
    }

    public function ownAccessToken(): ?string
    {
        $stored = (string) $this->get('meta_audiences_access_token');
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

    public function accessToken(): ?string
    {
        return $this->ownAccessToken() ?? MetaCapiSettings::for($this->siteId)->accessToken();
    }

    /** Leeg of null laat het opgeslagen token ongemoeid. */
    public function storeAccessToken(?string $token): void
    {
        $token = trim((string) $token);
        if ($token === '') {
            return;
        }

        Customsetting::set('meta_audiences_access_token', Crypt::encryptString($token), $this->siteId);
    }

    public function graphVersion(): string
    {
        return (string) config('services.meta.graph_version', 'v26.0');
    }

    public function ready(): bool
    {
        return $this->enabled() && $this->adAccountId() !== null && $this->accessToken() !== null;
    }

    protected function get(string $name): mixed
    {
        // Ongecachet: de sync draait in een langlevende worker.
        return Customsetting::get($name, $this->siteId, null, null, 'default', true);
    }
}
