<?php

namespace Dashed\DashedEcommerceCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Services\Attribution\AttributionTracker;

/**
 * Vangt UTM-parameters en click-IDs uit de query op en bewaart deze in de
 * sessie. Deze data wordt later gebruikt om carts en orders te verrijken zodat
 * we de herkomst van een bestelling kunnen analyseren.
 */
class CaptureAttributionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (! $this->shouldCapture($request)) {
            return $next($request);
        }

        try {
            AttributionTracker::captureFromRequest($request);
        } catch (\Throwable $e) {
            // Attribution-tracking mag de request nooit breken.
            report($e);
        }

        return $next($request);
    }

    protected function shouldCapture(Request $request): bool
    {
        // Alleen GET / HEAD requests waarmee mensen op pagina's komen.
        if (! in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) {
            return false;
        }

        // Sessie is vereist om touches op te slaan.
        if (! $request->hasSession()) {
            return false;
        }

        // Globale kill-switch via Customsetting.
        try {
            $enabled = Customsetting::get('attribution_tracking_enabled', null, true);
            if ($enabled === false || $enabled === '0' || $enabled === 0 || $enabled === 'false') {
                return false;
            }
        } catch (\Throwable $e) {
            // Bij fouten in customsettings: gewoon doorgaan, tracking is best-effort.
        }

        return true;
    }
}
