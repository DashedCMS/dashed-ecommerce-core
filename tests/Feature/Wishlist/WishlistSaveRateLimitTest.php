<?php

// WishlistPage::saveByEmail() is niet los van de Livewire-runtime te testen:
// new WishlistPage() en app()->make(WishlistPage::class) breken allebei op
// $this->validate(), dat de container-binding 'livewire' nodig heeft die
// buiten een echte request/mount-cyclus niet bestaat (BindingResolutionException:
// Target class [livewire] does not exist). Daarom test dit bestand de
// losgetrokken WishlistSaveThrottle-klasse in plaats van het component zelf.

use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Services\Wishlist\WishlistSaveThrottle;

beforeEach(function () {
    RateLimiter::clear('wishlist-save:'.request()->ip());
});

it('staat de eerste vijf pogingen per uur toe en blokkeert de zesde', function () {
    $throttle = new WishlistSaveThrottle();
    $ip = request()->ip();

    foreach (range(1, 5) as $poging) {
        expect($throttle->allow($ip))->toBeTrue();
        $throttle->hit($ip);
    }

    expect($throttle->allow($ip))->toBeFalse();
});

it('houdt ip-adressen los van elkaar', function () {
    $throttle = new WishlistSaveThrottle();

    foreach (range(1, 5) as $poging) {
        $throttle->hit('1.1.1.1');
    }

    expect($throttle->allow('1.1.1.1'))->toBeFalse()
        ->and($throttle->allow('2.2.2.2'))->toBeTrue();
});
