<?php

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

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

            $options = [];
            if (is_array($op->product_extras ?? null)) {
                $options['product_extras'] = $op->product_extras;
            }
            if (is_array($op->hidden_options ?? null)) {
                $options['hidden_options'] = $op->hidden_options;
            }

            cartHelper()->addToCart($op->product_id, (int) $op->quantity, $options);
        }

        if ($skipped > 0) {
            session()->flash('cart_recovery_skipped', $skipped);
        }

        $checkoutUrl = ShoppingCart::getCheckoutUrl();
        if (! $checkoutUrl || $checkoutUrl === '#') {
            $checkoutUrl = ShoppingCart::getCartUrl() ?: '/';
        }

        return redirect($checkoutUrl);
    }
}
