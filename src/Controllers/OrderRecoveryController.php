<?php

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\AbandonedCartClick;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;
use Dashed\DashedEcommerceCore\Services\Payments\PaymentFailure;

class OrderRecoveryController extends Controller
{
    public function resume(Request $request, string $order)
    {
        if (! $request->hasValidSignatureWhileIgnoring(['email_id', 'discount', 'type'])) {
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

        // Link AbandonedCartEmail (cancelled-order flow) zodat clicked_at +
        // converted_at correct gevuld worden zoals bij de cart-flow via
        // CartController::restoreCart. Zonder dit blijft de email zonder
        // klik/conversie-registratie ook al heeft de gebruiker via deze
        // signed URL ge-recovered.
        $emailId = $request->query('email_id');
        if ($emailId) {
            $abandonedEmail = AbandonedCartEmail::find($emailId);
            if ($abandonedEmail) {
                if (! $abandonedEmail->clicked_at) {
                    $abandonedEmail->update(['clicked_at' => now()]);
                }

                AbandonedCartClick::create([
                    'abandoned_cart_email_id' => $abandonedEmail->id,
                    'link_type' => $request->query('type', 'button'),
                ]);

                session(['abandoned_cart_email_id' => $abandonedEmail->id]);
            }
        }

        // Markeer downstream order ook als abandoned-cart recovery (gelijk
        // aan CartController::restoreCart). Wordt door Checkout::placeOrder
        // gepulled en op de nieuwe order opgeslagen.
        session(['abandoned_cart_recovery' => true]);

        // Dezelfde drie flash-waarden als de terugkeer van de PSP, zodat de
        // checkout een klant uit de mail net zo opvangt (uitleg, iDEAL voor,
        // afgewezen methode verborgen).
        PaymentFailure::flash($orderModel);

        // AbandonedCartMail zet de kortingscode van de stap als discount= achter
        // de knop-URL; de checkout past hem toe en valideert hem. Net als
        // CartController::restoreCart alleen accepteren wat er als code uitziet.
        $discount = (string) $request->query('discount', '');
        if ($discount !== '' && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $discount)) {
            session(['discountCode' => $discount]);
        }

        $checkoutUrl = ShoppingCart::getCheckoutUrl();
        if (! $checkoutUrl || $checkoutUrl === '#') {
            $checkoutUrl = ShoppingCart::getCartUrl() ?: '/';
        }

        return redirect($checkoutUrl);
    }
}
