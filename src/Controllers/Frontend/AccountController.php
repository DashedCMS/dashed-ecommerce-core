<?php

namespace Qubiqx\QcommerceEcommerceCore\Controllers\Frontend;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Artesaos\SEOTools\Facades\SEOTools;
use Qubiqx\QcommerceCore\Controllers\Frontend\FrontendController;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceCore\Requests\Frontend\UpdateAccountRequest;

class AccountController extends FrontendController
{
    public function orders()
    {
        if (View::exists('qcommerce.account.orders')) {
            SEOTools::setTitle(Translation::get('account-orders-page-meta-title', 'account', 'My orders'));
            SEOTools::setDescription(Translation::get('account-orders-page-meta-description', 'account', 'View your orders here'));
            SEOTools::opengraph()->setUrl(url()->current());

            return view('qcommerce.account.orders');
        } else {
            return $this->pageNotFound();
        }
    }
}
