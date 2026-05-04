<?php

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class AbandonedCartUnsubscribeController extends Controller
{
    public function unsubscribe(Request $request, int $record)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $email = AbandonedCartEmail::find($record);
        if (! $email || blank($email->email)) {
            abort(404);
        }

        $cancelled = AbandonedCartEmail::cancelPendingForEmail(
            $email->email,
            'unsubscribed_via_link',
        );

        return response()->view('dashed-ecommerce-core::emails.unsubscribe-confirmation', [
            'siteName' => \Dashed\DashedCore\Models\Customsetting::get('site_name'),
            'cancelledCount' => $cancelled,
            'flowLabel' => 'verlaten winkelwagen',
        ]);
    }
}
