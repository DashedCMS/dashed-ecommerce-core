<?php

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;

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

        // Annuleer alle openstaande inschrijvingen voor deze order, ongeacht trigger_status.
        $cancelledCount = OrderFlowEnrollment::query()
            ->where('order_id', $orderModel->id)
            ->whereNull('cancelled_at')
            ->update([
                'cancelled_at' => now(),
                'cancelled_reason' => 'unsubscribe',
                'updated_at' => now(),
            ]);

        // Backwards-compat: zet ook de legacy-kolom op de order zodat oude
        // code-paden die hierop checken correct blijven werken.
        $alreadyCancelled = $orderModel->handled_flow_cancelled_at !== null;
        if (! $alreadyCancelled) {
            $orderModel->forceFill(['handled_flow_cancelled_at' => now()])->save();
        }

        $siteName = class_exists(\Dashed\DashedCore\Models\Customsetting::class)
            ? \Dashed\DashedCore\Models\Customsetting::get('site_name')
            : config('app.name');

        return response()->view('dashed-ecommerce-core::emails.unsubscribe-confirmation', [
            'siteName' => $siteName,
            'cancelledCount' => $cancelledCount,
            'flowLabel' => 'order-opvolg',
        ]);
    }
}
