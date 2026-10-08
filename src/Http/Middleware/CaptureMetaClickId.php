<?php

namespace Dashed\DashedEcommerceCore\Http\Middleware;

use Closure;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bewaart een fbclid uit de URL in het fbc-formaat van Meta in de sessie.
 * Terugval voor orders waarbij de _fbc-cookie van de pixel ontbreekt
 * (adblocker, ITP). Best-effort: breekt nooit een verzoek.
 */
class CaptureMetaClickId
{
    public const SESSION_KEY = 'dashed_meta_fbc';

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $fbclid = $request->query('fbclid');

            if (in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)
                && $request->hasSession()
                && is_string($fbclid)
                && trim($fbclid) !== ''
                && strlen($fbclid) <= 900) {
                $request->session()->put(self::SESSION_KEY, static::fbcFromClickId(trim($fbclid)));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return $next($request);
    }

    public static function fbcFromClickId(string $fbclid, ?int $unixMs = null): string
    {
        return 'fb.1.' . ($unixMs ?? Carbon::now()->getTimestampMs()) . '.' . $fbclid;
    }
}
