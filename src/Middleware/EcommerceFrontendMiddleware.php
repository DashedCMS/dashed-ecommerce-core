<?php

namespace Dashed\DashedEcommerceCore\Middleware;

use Closure;
use Illuminate\Http\Request;

class EcommerceFrontendMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
