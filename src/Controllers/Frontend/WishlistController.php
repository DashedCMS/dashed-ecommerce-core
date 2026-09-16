<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedEcommerceCore\Models\Wishlist;
use Dashed\DashedEcommerceCore\Classes\WishlistHelper;
use Dashed\DashedEcommerceCore\Classes\EcommerceAccountHelper;

class WishlistController
{
    /** De link in mails: zet de lijst op dit apparaat en opent de lijstpagina. */
    public static function restoreUrl(Wishlist $wishlist): string
    {
        return route('dashed.frontend.wishlist.restore', ['token' => Crypt::encryptString($wishlist->token)]);
    }

    public function restore(Request $request)
    {
        try {
            $token = Crypt::decryptString((string) $request->query('token'));
        } catch (\Throwable) {
            return redirect('/');
        }

        $wishlist = Wishlist::where('token', $token)->first();

        if (! $wishlist) {
            return redirect('/');
        }

        wishlistHelper()->useToken($wishlist->token);

        // Een ingelogde gebruiker met een eigen lijst: samenvoegen gebeurt in
        // getWishlist() zodra die het token naast de gebruiker ziet.
        WishlistHelper::reset();
        wishlistHelper()->getWishlist();

        return redirect(EcommerceAccountHelper::getWishlistUrl());
    }

    public function shared(string $shareToken)
    {
        $wishlist = Wishlist::where('share_token', $shareToken)->firstOrFail();

        $view = config('dashed-core.site_theme', 'dashed') . '.wishlist.shared';

        return view(view()->exists($view) ? $view : 'dashed-ecommerce-core::templates.wishlist.shared', [
            'wishlist' => $wishlist,
            'items' => $wishlist->publicItems(),
        ]);
    }
}
