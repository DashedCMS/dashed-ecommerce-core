<?php

namespace Dashed\DashedEcommerceCore\Services\Meta;

use Closure;
use Illuminate\Http\Request;

/**
 * De ene plek die beslist of er marketingtoestemming is, voor alles wat in het
 * verzoek van de bezoeker naar Meta gaat (Contact, ViewContent, AddToCart,
 * InitiateCheckout) en voor de momentopname op OrderTracking. Volgorde: een
 * eigen resolver (resolveUsing()), anders de cookiebanner via ConsentGate van
 * dashed-core (oudere core heeft die niet), anders toegestaan zoals voorheen.
 * ConsentGate::allows() geeft zelf true zolang de banner uit staat.
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

        if (class_exists(\Dashed\DashedCore\Classes\Consent\ConsentGate::class)) {
            return \Dashed\DashedCore\Classes\Consent\ConsentGate::allows('marketing');
        }

        return true;
    }

    /**
     * Staat het toestemmingsbeheer (de cookiebanner) aan voor de huidige site?
     * Zonder banner is er geen keuze om vast te leggen.
     */
    public static function managed(): bool
    {
        return class_exists(\Dashed\DashedCore\Classes\Consent\ConsentGate::class)
            && \Dashed\DashedCore\Classes\Consent\ConsentGate::active();
    }

    /**
     * Een analytics-keuze, voor de momentopname op de order.
     */
    public static function analyticsGranted(): bool
    {
        if (class_exists(\Dashed\DashedCore\Classes\Consent\ConsentGate::class)) {
            return \Dashed\DashedCore\Classes\Consent\ConsentGate::allows('analytics');
        }

        return true;
    }
}
