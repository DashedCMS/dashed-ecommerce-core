<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Dashed\DashedEcommerceCore\Models\CustomerMatchEndpoint;
use Dashed\DashedEcommerceCore\Models\CustomerMatchAccessLog;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GoogleAdsBasicAuth
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $slug = (string) $request->route('slug');

        if (! $request->isSecure() && app()->environment('production')) {
            return $this->reject($request, $slug, 403, 'https_required');
        }

        $endpoint = CustomerMatchEndpoint::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($endpoint === null) {
            return $this->reject($request, $slug, 404, 'endpoint_not_found');
        }

        $providedUser = (string) $request->getUser();
        $providedPass = (string) $request->getPassword();

        if ($providedUser === '' || $providedPass === '') {
            return $this->challenge($request, $slug, 'missing_credentials', $endpoint->id);
        }

        $userMatches = hash_equals($endpoint->username, $providedUser);
        $passMatches = Hash::check($providedPass, $endpoint->password);

        if (! $userMatches || ! $passMatches) {
            return $this->challenge($request, $slug, 'invalid_credentials', $endpoint->id);
        }

        $endpoint->recordAccess($request->ip() ?? '');

        $request->attributes->set('customer_match_endpoint', $endpoint);

        return $next($request);
    }

    private function challenge(Request $request, string $slug, string $reason, ?int $endpointId): Response
    {
        $this->log($request, $slug, 401, $reason, $endpointId);

        return new Response('Unauthorized', 401, [
            'WWW-Authenticate' => 'Basic realm="Google Ads Customer Match"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function reject(Request $request, string $slug, int $status, string $reason): Response
    {
        $this->log($request, $slug, $status, $reason, null);

        return new Response('', $status, [
            'Cache-Control' => 'no-store',
        ]);
    }

    private function log(Request $request, string $slug, int $status, string $reason, ?int $endpointId): void
    {
        CustomerMatchAccessLog::create([
            'customer_match_endpoint_id' => $endpointId,
            'slug' => $slug,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'status' => $status,
            'failure_reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
