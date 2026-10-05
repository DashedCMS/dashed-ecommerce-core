<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * OSS (One Stop Shop): een particulier in een ander EU-land betaalt de btw van
 * zijn eigen land. Deze klasse kent de regels; wagen, orderregels en de
 * verzamelfactuur vragen het hier.
 */
class OssVat
{
    /** Standaardtarieven van de 27 EU-landen, stand 2026. */
    public const STANDARD_RATES = [
        'AT' => 20, 'BE' => 21, 'BG' => 20, 'HR' => 25, 'CY' => 19, 'CZ' => 21, 'DK' => 25,
        'EE' => 24, 'FI' => 25.5, 'FR' => 20, 'DE' => 19, 'GR' => 24, 'HU' => 27, 'IE' => 23,
        'IT' => 22, 'LV' => 21, 'LT' => 21, 'LU' => 17, 'MT' => 18, 'NL' => 21, 'PL' => 23,
        'PT' => 23, 'RO' => 21, 'SK' => 23, 'SI' => 22, 'ES' => 21, 'SE' => 25,
    ];

    /** @var array<string, string|null> landtekst → ISO-code */
    protected static array $isoCodes = [];

    /** @var array<string, string>|null ISO-code → Nederlandse naam */
    protected static ?array $countryNames = null;

    public static function flush(): void
    {
        static::$isoCodes = [];
        static::$countryNames = null;
    }

    public static function enabled(?string $siteId = null): bool
    {
        return (bool) Customsetting::get('oss_enabled', $siteId);
    }

    public static function homeCountryCode(?string $siteId = null): string
    {
        return static::isoCode(Customsetting::get('company_country', $siteId)) ?: 'NL';
    }

    /**
     * @return array<string, float>
     */
    public static function rates(?string $siteId = null): array
    {
        $rates = array_map('floatval', self::STANDARD_RATES);

        $overrides = Customsetting::get('oss_vat_rates', $siteId);
        if (is_string($overrides)) {
            $overrides = json_decode($overrides, true);
        }

        foreach (is_array($overrides) ? $overrides : [] as $code => $rate) {
            $code = strtoupper(trim((string) $code));
            if (isset($rates[$code]) && is_numeric($rate)) {
                $rates[$code] = (float) $rate;
            }
        }

        return $rates;
    }

    /**
     * De ISO-code van het OSS-land, of null als dit geen OSS-verkoop is.
     */
    public static function destination(?string $country, bool $reverseCharge, bool $shipped, ?string $siteId = null): ?string
    {
        if ($reverseCharge || ! $shipped || ! static::enabled($siteId)) {
            return null;
        }

        $code = static::isoCode($country);

        if (! $code || $code === static::homeCountryCode($siteId) || ! isset(self::STANDARD_RATES[$code])) {
            return null;
        }

        return $code;
    }

    public static function destinationFor(Order $order): ?string
    {
        $country = filled($order->country) ? $order->country : $order->invoice_country;

        return static::destination(
            $country,
            (bool) $order->vat_reverse_charge,
            static::isShipped($order),
            $order->site_id,
        );
    }

    /**
     * Ophalen is geen verzending, een kassaorder zonder verzendmethode ook niet.
     * Bol- en Etsy-orders hebben geen verzendmethode en zijn altijd verzonden.
     */
    public static function isShipped(Order $order): bool
    {
        $shippingMethod = $order->shipping_method_id ? $order->shippingMethod : null;

        if ($shippingMethod) {
            return $shippingMethod->sort !== 'take_away';
        }

        return strtolower((string) $order->order_origin) !== 'pos';
    }

    /**
     * Alleen het eigen standaardtarief wordt het standaardtarief van het land;
     * 0%, 9% of een al omgezet tarief blijft staan.
     */
    public static function rateFor(string $countryCode, float $homeRate, ?string $siteId = null): float
    {
        $rates = static::rates($siteId);
        $homeStandard = $rates[static::homeCountryCode($siteId)] ?? 21.0;

        if (abs($homeRate - $homeStandard) > 0.001) {
            return $homeRate;
        }

        return $rates[strtoupper($countryCode)] ?? $homeRate;
    }

    public static function rateForOrder(?Order $order, float $rate): float
    {
        $countryCode = $order ? static::destinationFor($order) : null;

        return $countryCode ? static::rateFor($countryCode, $rate, $order->site_id) : $rate;
    }

    public static function rateKey(float $rate): string
    {
        $key = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');

        return $key === '' ? '0' : $key;
    }

    public static function countryName(string $countryCode): string
    {
        if (static::$countryNames === null) {
            static::$countryNames = [];
            foreach (Countries::getCountries() as $country) {
                static::$countryNames[$country['alpha2Code']] = $country['translations']['nl'] ?? $country['name'];
            }
        }

        return static::$countryNames[strtoupper($countryCode)] ?? $countryCode;
    }

    /**
     * Zet order->btw en vat_percentages gelijk aan de som van de regels. Voor
     * importen en de kassa, die de order-btw vóór de regels met 21% uitrekenen.
     */
    public static function recalculateOrderVat(Order $order): void
    {
        if (! static::destinationFor($order)) {
            return;
        }

        $btw = 0.0;
        $perRate = [];

        foreach ($order->orderProducts()->get() as $line) {
            $lineVat = (float) $line->btw;
            $btw += $lineVat;

            $key = static::rateKey((float) ($line->vat_rate ?? 0));
            if ($key !== '0') {
                $perRate[$key] = ($perRate[$key] ?? 0.0) + $lineVat;
            }
        }

        $order->btw = round($btw, 2);
        $order->vat_percentages = array_map(fn ($amount) => round($amount, 2), $perRate);
        $order->saveQuietly();
    }

    protected static function isoCode(?string $country): ?string
    {
        $country = trim((string) $country);

        if ($country === '') {
            return null;
        }

        $cacheKey = mb_strtolower($country);

        if (! array_key_exists($cacheKey, static::$isoCodes)) {
            $code = Countries::getCountryIsoCode($country);
            static::$isoCodes[$cacheKey] = $code ? strtoupper($code) : null;
        }

        return static::$isoCodes[$cacheKey];
    }
}
