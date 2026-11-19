<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;

/**
 * De gedeelde verwijslink. Zet de verwijscode als kortingscode klaar voor de
 * checkout (die leest session('discountCode')) en stuurt door naar de
 * homepage. Of de bezoeker de korting echt krijgt, beslist de checkout zodra
 * het e-mailadres bekend is.
 */
class ReferralController extends Controller
{
    public function __invoke(Request $request, string $code)
    {
        $referralCode = ReferralProgram::findByCode($code);

        if (! $referralCode || ! ReferralProgram::enabled($referralCode->site_id)) {
            return redirect('/');
        }

        ReferralProgram::syncDiscountCode($referralCode);

        session(['discountCode' => $referralCode->code]);

        rescue(fn () => cartHelper()->applyDiscountCode($referralCode->code), report: false);

        return redirect('/')->with('success', Translation::get('referral-welcome', 'referral', 'Welkom! Je korting voor je eerste bestelling staat klaar in je winkelwagen.'));
    }
}
