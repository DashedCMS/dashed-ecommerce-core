<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Dashed\DashedEcommerceCore\Models\ApiClient;

/**
 * Bearer-sleutel van een ApiClient, met de gevraagde ability:
 * ->middleware(AuthenticateApiClient::class . ':orders.export').
 */
class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        // Validatiefouten als JSON, ook zonder Accept-header (anders een redirect).
        $request->headers->set('Accept', 'application/json');

        $token = (string) $request->bearerToken();
        $client = $token !== '' ? ApiClient::findByToken($token) : null;

        if (! $client || ! $client->is_active) {
            return response()->json(['message' => 'Ongeldige of ingetrokken API-sleutel.'], 401);
        }

        if (! $client->hasAbility($ability)) {
            return response()->json(['message' => 'Deze API-sleutel heeft geen toegang tot dit endpoint.'], 403);
        }

        if (! $client->last_used_at || $client->last_used_at->lt(now()->subMinute())) {
            $client->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        $request->attributes->set('api_client', $client);

        return $next($request);
    }
}
