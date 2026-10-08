<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Closure;
use Illuminate\Http\Request;

/**
 * De ene plek die beslist of er marketingtoestemming is. Er is nog geen
 * cookiebanner: de pixel laadt onvoorwaardelijk en deze klasse volgt dat. Een
 * banner sluit hier aan met resolveUsing() en houdt dan pixel en server-side
 * events samen tegen.
 */
class MarketingConsent
{
    protected static ?Closure $resolver = null;

    public static function resolveUsing(?Closure $resolver): void
    {
        static::$resolver = $resolver;
    }

    public static function granted(Request $request, ?string $siteId = null): bool
    {
        if (static::$resolver !== null) {
            return (bool) (static::$resolver)($request, $siteId);
        }

        return true;
    }
}
