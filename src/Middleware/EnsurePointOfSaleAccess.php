<?php

namespace Dashed\DashedEcommerceCore\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Dashed\DashedCore\Middleware\EnsureMfaIsFresh;
use Dashed\DashedCore\Middleware\EnsureMfaIsSetUp;
use Dashed\DashedCore\Middleware\EnsureCmsIpAllowed;
use Dashed\DashedCore\Middleware\EnsureCmsSessionIsActive;
use Dashed\DashedEcommerceCore\Filament\Pages\POS\POSPageRedirect;

/**
 * De kassa draait buiten het Filament-paneel (eigen routes onder /ecommerce
 * en /api/point-of-sale), dus de beveiliging van het paneel gold daar niet:
 * geen IP-lijst, geen sessielimieten, geen MFA, en de API vroeg alleen om
 * een back-office account, niet om het recht view_pos. stack() geeft de
 * middleware die de kassaroutes daarom krijgen.
 *
 * Deze klasse staat vooraan in die stack: ze toetst het kassarecht (zelfde
 * regel als POSPageRedirect::canAccess()) en zet een omleiding van de
 * paneelmiddleware erachter (naar de MFA-pagina of de inlogpagina) om in
 * een 403 met JSON voor een fetch-verzoek van de kassa, dat anders de
 * HTML van die pagina als JSON probeert te lezen.
 *
 * De paneelmiddleware staat los in de routelijst en niet hierbinnen, want
 * Livewire herhaalt alleen middleware die hij op de route van de pagina
 * terugvindt; zo lopen ook de Livewire-verzoeken van de kassa erdoor.
 */
class EnsurePointOfSaleAccess
{
    /**
     * @return array<int, class-string>
     */
    public static function stack(): array
    {
        $panelMiddleware = array_filter([
            EnsureCmsIpAllowed::class,
            EnsureCmsSessionIsActive::class,
            EnsureMfaIsSetUp::class,
            EnsureMfaIsFresh::class,
        ], fn (string $class): bool => class_exists($class));

        return [static::class, ...array_values($panelMiddleware)];
    }

    public function handle(Request $request, Closure $next)
    {
        if (! POSPageRedirect::canAccess()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Je hebt geen toegang tot de kassa.'),
                ], 403);
            }

            abort(403, __('Je hebt geen toegang tot de kassa.'));
        }

        $response = $next($request);

        if ($response instanceof RedirectResponse && $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('Je sessie vraagt om een nieuwe controle; ververs de kassa.'),
                'redirect' => $response->getTargetUrl(),
            ], 403);
        }

        return $response;
    }
}
