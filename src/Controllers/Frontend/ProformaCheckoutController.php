<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Dashed\DashedEcommerceCore\Models\Order;

class ProformaCheckoutController
{
    public function show(Request $request, string $orderHash)
    {
        $order = Order::where('hash', $orderHash)->where('is_proforma', true)->firstOrFail();

        if ($order->isPaidFor()) {
            return response()->view('dashed-ecommerce-core::proforma.already-paid', ['order' => $order]);
        }

        return view('dashed-ecommerce-core::proforma.checkout', ['order' => $order]);
    }
}
