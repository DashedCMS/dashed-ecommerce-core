<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\InvoiceAccess;
use Dashed\DashedEcommerceCore\Models\AbandonedCartClick;
use Dashed\DashedEcommerceCore\Models\AbandonedCartEmail;

class CartController extends Controller
{
    public function restoreCart(Request $request)
    {
        try {
            $cartToken = Crypt::decryptString($request->query('cart'));
        } catch (\Throwable $e) {
            return redirect('/');
        }

        $abandonedCart = CartModel::with('items')->where('token', $cartToken)->first();

        if (! $abandonedCart || $abandonedCart->items->isEmpty()) {
            return redirect('/');
        }

        // Leeg de huidige cart (van de browser-cookie) en kopieer de
        // items uit de abandoned cart erin. We swappen NIET de cookie
        // zodat de gebruiker zijn eigen cart-context houdt; alleen de
        // inhoud wordt gerestaureerd. Items zonder product_id (custom
        // line-items) worden overgeslagen omdat de mail die ook al
        // niet liet zien.
        cartHelper()->emptyCart();

        foreach ($abandonedCart->items as $item) {
            if (empty($item->product_id) || ! $item->product) {
                continue;
            }
            $options = is_array($item->options) ? $item->options : [];
            cartHelper()->addToCart($item->product_id, (int) $item->quantity, $options);
        }

        $linkType = $request->query('type', 'button');
        $emailId = $request->query('email_id');

        if ($emailId) {
            $abandonedEmail = AbandonedCartEmail::find($emailId);
            if ($abandonedEmail && $abandonedEmail->cart_id === $abandonedCart->id) {
                if (! $abandonedEmail->clicked_at) {
                    $abandonedEmail->update(['clicked_at' => now()]);
                }

                AbandonedCartClick::create([
                    'abandoned_cart_email_id' => $abandonedEmail->id,
                    'link_type' => $linkType,
                ]);

                session(['abandoned_cart_email_id' => $abandonedEmail->id]);
            }
        }

        session(['abandoned_cart_recovery' => true]);

        // Alleen accepteren wat er als kortingscode uitziet. Google Ads en andere
        // tracker-rewrites hebben in productie soms een hele URL in de discount
        // querystring gezet; die rommel willen we niet in de sessie hebben en
        // ook niet als "geweigerde code" loggen.
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

    public function downloadInvoice(Request $request, $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        if (! $order) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        abort_unless(InvoiceAccess::allows($request, $order), 403);

        if (! $order->downloadInvoiceUrl()) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return Storage::disk('dashed')->download($order->invoicePath());
    }

    public function downloadPackingSlip(Request $request, $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        if (! $order) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        abort_unless(InvoiceAccess::allows($request, $order), 403);

        if (! $order->downloadPackingslipUrl()) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return Storage::disk('dashed')->download($order->packingSlipPath());
    }
}
