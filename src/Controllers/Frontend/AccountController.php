<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Support\Facades\View;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedCore\Controllers\Frontend\FrontendController;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Account\Orders;

class AccountController extends FrontendController
{
    public function orders()
    {
        if (View::exists(config('dashed-core.site_theme') . '.account.orders')) {
            seo()->metaData('metaTitle', Translation::get('account-orders-page-meta-title', 'account', 'My orders'));
            seo()->metaData('metaDescription', Translation::get('account-orders-page-meta-description', 'account', 'View your orders here'));

            return view('dashed-core::layouts.livewire-master', [
                'livewireComponent' => Orders::class,
            ]);

            return view(config('dashed-core.site_theme') . '.account.orders');
        } else {
            return $this->pageNotFound();
        }
    }
}
