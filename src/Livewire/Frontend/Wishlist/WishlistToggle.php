<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Wishlist;

use Livewire\Component;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Het hartje. De beginstaat komt uit wishlistHelper()->productIds(): één query
 * per paginaweergave, hoeveel kaartjes er ook staan.
 */
class WishlistToggle extends Component
{
    public Product $product;

    public bool $inWishlist = false;

    public bool $showLabel = false;

    public function mount(Product $product, bool $showLabel = false): void
    {
        $this->product = $product;
        $this->showLabel = $showLabel;
        $this->inWishlist = wishlistHelper()->has($product);
    }

    public function toggle(): void
    {
        $this->inWishlist = wishlistHelper()->toggle($this->product);
        $this->dispatch('refreshWishlist');
        $this->dispatch('wishlistToggled', productId: $this->product->id, inWishlist: $this->inWishlist);
    }

    public function render()
    {
        if (! Customsetting::get('wishlist_enabled', Sites::getActive(), 1)) {
            return '<span></span>';
        }

        return view(config('dashed-core.site_theme', 'dashed') . '.wishlist.toggle');
    }
}
