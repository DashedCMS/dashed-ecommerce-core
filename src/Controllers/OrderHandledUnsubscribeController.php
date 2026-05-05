<?php

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;

class OrderHandledUnsubscribeController extends Controller
{
    public function unsubscribe(Request $request, int $order)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $orderModel = Order::find($order);
        if (! $orderModel) {
            abort(404);
        }

        $alreadyCancelled = $orderModel->handled_flow_cancelled_at !== null;
        if (! $alreadyCancelled) {
            $orderModel->forceFill(['handled_flow_cancelled_at' => now()])->save();
        }

        $siteName = class_exists(\Dashed\DashedCore\Models\Customsetting::class)
            ? \Dashed\DashedCore\Models\Customsetting::get('site_name')
            : config('app.name');

        return response()->view('dashed-ecommerce-core::emails.unsubscribe-confirmation', [
            'siteName' => $siteName,
            'cancelledCount' => $alreadyCancelled ? 0 : 1,
            'flowLabel' => 'order-opvolg',
        ]);
    }
}
