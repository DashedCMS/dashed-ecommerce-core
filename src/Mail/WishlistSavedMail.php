<?php

namespace Dashed\DashedEcommerceCore\Mail;

use Illuminate\Mail\Mailable;
use Dashed\DashedEcommerceCore\Models\Wishlist;

/**
 * Stub: alleen de constructor en een geldige build() zodat de imports in
 * WishlistPage kloppen. De echte inhoud (onderwerp, view, herstel-link) komt
 * in Task 9.
 */
class WishlistSavedMail extends Mailable
{
    public function __construct(public readonly Wishlist $wishlist)
    {
    }

    public function build(): static
    {
        return $this->html('');
    }
}
