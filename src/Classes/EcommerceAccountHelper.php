<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedPages\Models\Page;
use Dashed\DashedCore\Models\Customsetting;

class EcommerceAccountHelper
{
    public static function getAccountOrdersUrl()
    {
        $pageId = auth()->check() ? Customsetting::get('orders_page_id') : Customsetting::get('login_page_id');
        $page = Page::publicShowable()->where('id', $pageId)->first();

        return $page->getUrl() ?? '#';
    }

    /** De verlanglijstpagina, publiek voor gast én klant. */
    public static function getWishlistUrl(): string
    {
        $page = Page::publicShowable()->where('id', Customsetting::get('wishlist_page_id'))->first();

        return $page?->getUrl() ?? '#';
    }
}
