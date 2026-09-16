<?php

namespace Dashed\DashedEcommerceCore\Livewire\Frontend\Wishlist;

use Livewire\Component;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Models\WishlistItem;
use Dashed\DashedEcommerceCore\Mail\WishlistSavedMail;
use Dashed\DashedEcommerceCore\Services\Wishlist\WishlistSaveThrottle;

class WishlistPage extends Component
{
    public string $email = '';

    public ?string $shareUrl = null;

    public ?string $message = null;

    protected $listeners = ['refreshWishlist' => '$refresh'];

    public function mount(): void
    {
        $wishlist = wishlistHelper()->getWishlist();

        if ($wishlist?->share_token) {
            $this->shareUrl = route('dashed.frontend.wishlist.shared', ['shareToken' => $wishlist->share_token]);
        }
    }

    public function remove(int $productId): void
    {
        wishlistHelper()->remove($productId);
        $this->dispatch('refreshWishlist');
    }

    public function addToCart(int $productId): void
    {
        $item = $this->items()->firstWhere('product_id', $productId);

        if (! $item || ! $item->product->inStock()) {
            $this->message = __('Dit product is niet op voorraad.');

            return;
        }

        cartHelper()->addToCart($productId, 1, []);
        $this->dispatch('refreshCart');
        $this->dispatch('productAddedToCart', product: $item->product);
    }

    public function addAllToCart(): void
    {
        $toegevoegd = 0;

        foreach ($this->items() as $item) {
            if (! $item->product->inStock()) {
                continue;
            }

            cartHelper()->addToCart($item->product_id, 1, []);
            $toegevoegd++;
        }

        $this->dispatch('refreshCart');
        $this->message = trans_choice('{0} Niets toegevoegd: alles is uitverkocht.|{1} 1 product in je winkelwagen gezet.|[2,*] :count producten in je winkelwagen gezet.', $toegevoegd, ['count' => $toegevoegd]);
    }

    public function saveByEmail(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        if ($this->items()->isEmpty()) {
            $this->message = __('Zet eerst iets op je verlanglijst.');

            return;
        }

        // Zonder limiet is dit formulier een gratis spam-relay: elk e-mailadres
        // krijgt een mail vanaf de afzender van de shop zelf, ongeacht wie het
        // invult. Zelfde patroon als OutletBidService in Lovora.
        $ip = request()->ip();
        $throttle = new WishlistSaveThrottle();

        if (! $throttle->allow($ip)) {
            $this->message = __('Je hebt dit net al gedaan. Probeer het over een uur nog eens.');

            return;
        }

        $throttle->hit($ip);

        $wishlist = wishlistHelper()->getWishlist(create: true);
        wishlistHelper()->adoptEmail($this->email);

        \Illuminate\Support\Facades\Mail::to($this->email)->send(new WishlistSavedMail($wishlist->fresh()));

        $this->message = __('We hebben je verlanglijst bewaard en je een link gemaild.');
    }

    public function share(): void
    {
        $wishlist = wishlistHelper()->getWishlist(create: true);

        $this->shareUrl = route('dashed.frontend.wishlist.shared', ['shareToken' => $wishlist->ensureShareToken()]);
    }

    /** @return Collection<int, WishlistItem> */
    public function items(): Collection
    {
        if (! Customsetting::get('wishlist_enabled', Sites::getActive(), 1)) {
            return collect();
        }

        return wishlistHelper()->getWishlist()?->publicItems() ?? collect();
    }

    public function render()
    {
        $wishlist = wishlistHelper()->getWishlist();

        return view(config('dashed-core.site_theme', 'dashed') . '.wishlist.wishlist', [
            'wishlist' => $wishlist,
            'items' => $this->items(),
            'hasEmail' => (bool) ($wishlist?->email),
        ]);
    }
}
