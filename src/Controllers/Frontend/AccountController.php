<?php

namespace Qubiqx\QcommerceEcommerceCore\Controllers\Frontend;

use Illuminate\Support\Facades\View;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceCore\Controllers\Frontend\FrontendController;

class AccountController extends FrontendController
{
    public function orders()
    {
        if (View::exists('qcommerce.account.orders')) {
            seo()->metaData('metaTitle', Translation::get('account-orders-page-meta-title', 'account', 'My orders'));
            seo()->metaData('metaDescription', Translation::get('account-orders-page-meta-description', 'account', 'View your orders here'));

            return view('qcommerce.account.orders');
        } else {
            return $this->pageNotFound();
        }
    }
}
