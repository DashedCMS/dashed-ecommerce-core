<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Middleware;

use Closure;
use Dashed\DashedEcommerceCore\Models\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrinter
{
    public function handle(Request $request, Closure $next): Response
    {
        $printer = $request->user();

        if (! $printer instanceof Printer) {
            abort(403, 'Geen geldige printer token');
        }

        if (! $printer->is_active) {
            abort(403, 'Printer is inactief');
        }

        if (! $printer->last_ping_at || $printer->last_ping_at->lt(now()->subSeconds(5))) {
            $printer->forceFill(['last_ping_at' => now()])->saveQuietly();
        }

        $request->attributes->set('printer', $printer);

        return $next($request);
    }
}
