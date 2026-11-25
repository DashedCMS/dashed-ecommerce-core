<?php

namespace Dashed\DashedEcommerceCore\Middleware;

use Closure;
use Illuminate\Http\Request;
use Dashed\DashedCore\Models\Customsetting;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kassa in de app: zelfde regel als de kassa in het CMS
 * (POSPageRedirect::canAccess()): de kassa staat aan voor de actieve site
 * (pos_enabled) en de gebruiker heeft view_pos. De app-ability pos.use op
 * dezelfde routes leunt al op view_pos; de instelling kent het token niet.
 */
class EnsureMobilePosAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->can('view_pos') || ! Customsetting::get('pos_enabled', null, false)) {
            return response()->json([
                'success' => false,
                'message' => __('Je hebt geen toegang tot de kassa.'),
            ], 403);
        }

        return $next($request);
    }
}
