<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Http\Middleware\CaptureMetaClickId;

/**
 * Wat Meta van een bezoeker wil weten, uit het lopende verzoek gehaald en
 * opgeschoond. Alles komt van de browser en kan leeg, te lang of onzin zijn:
 * elke methode geeft dan null in plaats van iets door te laten.
 */
class RequestSignals
{
    /** Crawlers, previews, monitoring en scripts: die krijgen geen server-event. */
    public const BOT_PATTERN = '/bot\b|bot[\/;) ]|crawl|spider|slurp|mediapartners|facebookexternalhit|facebookcatalog|meta-external|whatsapp|telegram|preview|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|headless|phantomjs|python|curl|wget|httpclient|okhttp|go-http|java\/|libwww|scrapy|axios|node-fetch|guzzle|postman|insomnia|ahrefs|semrush|screaming|bytespider|gptbot|claudebot|ccbot|chatgpt|perplexity|dataprovider|feedfetcher|validator/i';

    /** Alleen waarden in Meta's eigen formaat (fb.{n}.{ms}.{rest}) en binnen de lengte. */
    public static function metaCookie(mixed $value, int $maxLength = 1000): ?string
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

    /** Ongeldige bytes eruit en nooit midden in een teken knippen. */
    public static function text(?string $value, int $maxBytes): ?string
    {
        $value = trim(mb_scrub((string) $value, 'UTF-8'));

        return $value === '' ? null : mb_strcut($value, 0, $maxBytes, 'UTF-8');
    }

    /**
     * @param  array<string,mixed>|null  $cookies  standaard $_COOKIE: Laravel
     *   gooit door JavaScript gezette (onversleutelde) cookies weg.
     */
    public static function fbp(?array $cookies = null): ?string
    {
        $cookies ??= $_COOKIE;

        return static::metaCookie($cookies['_fbp'] ?? null, 255);
    }

    /**
     * De _fbc-cookie van de pixel, anders de fbclid die de middleware in de
     * sessie zette, anders een fbclid in de URL van de pagina zelf.
     *
     * @param  array<string,mixed>|null  $cookies
     */
    public static function fbc(Request $request, ?array $cookies = null): ?string
    {
        $cookies ??= $_COOKIE;

        if ($fbc = static::metaCookie($cookies['_fbc'] ?? null)) {
            return $fbc;
        }

        $fromSession = $request->hasSession() ? $request->session()->get(CaptureMetaClickId::SESSION_KEY) : null;
        if ($fbc = static::metaCookie($fromSession)) {
            return $fbc;
        }

        $fbclid = static::fbclidFromUrl(static::rawPageUrl($request));

        return $fbclid ? static::metaCookie(CaptureMetaClickId::fbcFromClickId($fbclid)) : null;
    }

    public static function userAgent(Request $request): ?string
    {
        return static::text($request->userAgent(), 1000);
    }

    /** Geen user agent telt ook als bot: een echte browser stuurt er altijd een. */
    public static function isBot(Request $request): bool
    {
        $userAgent = static::userAgent($request);

        return $userAgent === null || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }

    /**
     * De pagina waarop de bezoeker staat, zonder querystring en fragment: daar
     * kunnen namen, tokens of e-mailadressen in staan en Meta heeft ze niet nodig.
     */
    public static function pageUrl(Request $request): ?string
    {
        $url = static::rawPageUrl($request);
        if ($url === null) {
            return null;
        }

        $url = preg_replace('/[?#].*$/', '', $url);

        return static::text($url, 2048);
    }

    public static function ip(Request $request): ?string
    {
        $ip = trim((string) $request->ip());

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    /**
     * Een Livewire-update en het ViewContent-verzoek van de browser gaan naar
     * een eigen endpoint; de Referer is dan de echte pagina. Bij een gewone
     * paginaweergave (het openen van de checkout) is de Referer juist de vorige
     * pagina: dan telt de URL van het verzoek zelf.
     */
    protected static function rawPageUrl(Request $request): ?string
    {
        $background = $request->headers->has('X-Livewire') || ! $request->isMethod('GET');
        $url = static::text($background ? $request->headers->get('referer') : $request->fullUrl(), 4096);

        if ($url === null || ! preg_match('#^https?://#i', $url) || str_contains($url, '/livewire/')) {
            return null;
        }

        return $url;
    }

    protected static function fbclidFromUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $fbclid = $query['fbclid'] ?? null;

        return is_string($fbclid) && trim($fbclid) !== '' && strlen($fbclid) <= 900 ? trim($fbclid) : null;
    }
}
