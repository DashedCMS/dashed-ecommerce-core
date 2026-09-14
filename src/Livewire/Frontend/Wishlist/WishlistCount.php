<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Wishlist;

use Livewire\Component;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;

class WishlistCount extends Component
{
    protected $listeners = ['refreshWishlist'];

    public function refreshWishlist(): void
    {
        // render() leest het aantal vers; hier hoeft niets te gebeuren.
    }

    public function placeholder()
    {
        return '<span class="wishlist-count" aria-hidden="true"></span>';
    }

    public function render()
    {
        $count = Customsetting::get('wishlist_enabled', Sites::getActive(), 1) ? wishlistHelper()->count() : 0;

        return view(config('dashed-core.site_theme', 'dashed') . '.wishlist.count', [
            'wishlistCount' => $count,
        ]);
    }
}
