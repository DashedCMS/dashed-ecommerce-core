<?php

namespace Dashed\DashedEcommerceCore\Services\Wishlist;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Losstaand van WishlistPage zodat de limiet ook zonder de Livewire-runtime
 * te testen is (het component zelf is dat niet, zie
 * tests/Feature/Wishlist/WishlistSaveRateLimitTest.php).
 */
class WishlistSaveThrottle
{
    protected int $maxAttempts = 5;

    protected int $decaySeconds = 3600;

    protected function key(string $ip): string
    {
        return 'wishlist-save:'.$ip;
    }

    public function allow(string $ip): bool
    {
        return ! RateLimiter::tooManyAttempts($this->key($ip), $this->maxAttempts);
    }

    public function hit(string $ip): void
    {
        RateLimiter::hit($this->key($ip), $this->decaySeconds);
    }
}
