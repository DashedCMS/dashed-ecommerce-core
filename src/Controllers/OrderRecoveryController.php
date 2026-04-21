<?php

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;

class OrderRecoveryController extends Controller
{
    public function resume(Request $request, string $order)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $orderModel = Order::where('hash', $order)->firstOrFail();

        cartHelper()->emptyCart();

        $skipped = 0;
        foreach ($orderModel->orderProducts as $op) {
            if (! $op->product_id || ! $op->product) {
                $skipped++;

                continue;
            }

            cartHelper()->addToCart($op->product_id, (int) $op->quantity);
        }

        if ($skipped > 0) {
            session()->flash('cart_recovery_skipped', $skipped);
        }

        return redirect('/checkout');
    }
}
