<?php

namespace Qubiqx\QcommerceEcommerceCore\Controllers\Frontend;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Artesaos\SEOTools\Facades\SEOTools;
use Qubiqx\QcommerceCore\Classes\Sites;
use Gloudemans\Shoppingcart\Facades\Cart;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;
use Qubiqx\QcommerceCore\Controllers\Frontend\FrontendController;

class CartController extends FrontendController
{
    public function cart()
    {
        ShoppingCart::removeInvalidItems();

        if (View::exists('qcommerce.cart.show')) {
            SEOTools::setTitle(Translation::get('cart-page-meta-title', 'cart', 'Cart'));
            SEOTools::setDescription(Translation::get('cart-page-meta-description', 'cart', 'View your shopping cart here'));
            SEOTools::opengraph()->setUrl(url()->current());

            return view('qcommerce.cart.show');
        } else {
            return $this->pageNotFound();
        }
    }

    public function checkout()
    {
        ShoppingCart::removeInvalidItems();

        if (View::exists('qcommerce.checkout.show')) {
            SEOTools::setTitle(Translation::get('checkout-page-meta-title', 'cart', 'Pay now'));
            SEOTools::setDescription(Translation::get('checkout-page-meta-description', 'cart', 'Finish your order'));
            SEOTools::opengraph()->setUrl(url()->current());

            return view('qcommerce.checkout.show');
        } else {
            return $this->pageNotFound();
        }
    }

    public function applyDiscountCode(Request $request)
    {
        if (! $request->discount_code) {
            session(['discountCode' => '']);

            ShoppingCart::removeInvalidItems();

            return redirect()->back();
        }

        $discountCode = DiscountCode::usable()->where('code', $request->discount_code)->first();

        if (! $discountCode || ! $discountCode->isValidForCart()) {
            session(['discountCode' => '']);

            ShoppingCart::removeInvalidItems();

            return redirect()->back()->with('error', Translation::get('discount-code-not-valid', 'cart', 'The discount code is not valid'));
        }

        session(['discountCode' => $discountCode->code]);

        ShoppingCart::removeInvalidItems();

        return redirect()->back()->with('success', Translation::get('discount-code-applied', 'cart', 'The discount code has been applied and discount has been calculated'));
    }

    public function addToCart(Request $request, Product $product)
    {
        $quantity = $request->qty;
        if (! $quantity || ! is_numeric($quantity)) {
            $quantity = 1;
        }

        $cartItems = ShoppingCart::cartItems();
        $cartUpdated = false;
        $productPrice = $product->currentPrice;
        $options = [];
        foreach ($product->allProductExtras() as $productExtra) {
            if ($productExtra->type == 'single') {
                $productValue = $request['product-extra-' . $productExtra->id];
                if ($productExtra->required && ! $productValue) {
                    ShoppingCart::removeInvalidItems();

                    return redirect()->back()->with('error', Translation::get('not-all-required-options-chosen', 'cart', 'Not all extra`s have a selected option.'))->withInput();
                }

                if ($productValue) {
                    $productExtraOption = ProductExtraOption::find($productValue);
                    $options[$productExtraOption->id] = [
                        'name' => $productExtra->name,
                        'value' => $productExtraOption->value,
                    ];
                    if ($productExtraOption->calculate_only_1_quantity) {
                        $productPrice = $productPrice + ($productExtraOption->price / $quantity);
                    } else {
                        $productPrice = $productPrice + $productExtraOption->price;
                    }
                }
            } else {
                foreach ($productExtra->productExtraOptions as $option) {
                    $productOptionValue = $request['product-extra-' . $productExtra->id . '-' . $option->id];
                    if ($productExtra->required && ! $productOptionValue) {
                        ShoppingCart::removeInvalidItems();

                        return redirect()->back()->with('error', Translation::get('not-all-required-options-chosen', 'cart', 'Not all extra`s have a selected option.'))->withInput();
                    }

                    if ($productOptionValue) {
                        $options[$option->id] = [
                            'name' => $productExtra->name,
                            'value' => $option->value,
                        ];
                        if ($option->calculate_only_1_quantity) {
                            $productPrice = $productPrice + ($option->price / $quantity);
                        } else {
                            $productPrice = $productPrice + $option->price;
                        }
                    }
                }
            }
        }

        foreach ($cartItems as $cartItem) {
            //Todo: the comparison for options does not work
            if ($cartItem->model->id == $product->id && $options == $cartItem->options) {
                $newQuantity = $cartItem->qty + $quantity;

                if ($product->limit_purchases_per_customer && $newQuantity > $product->limit_purchases_per_customer_limit) {
                    Cart::update($cartItem->rowId, $product->limit_purchases_per_customer_limit);

                    ShoppingCart::removeInvalidItems();

                    return redirect()->back()->with('error', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                        'quantity' => $product->limit_purchases_per_customer_limit,
                    ]))->withInput();
                }

                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (! $cartUpdated) {
            if ($product->limit_purchases_per_customer && $quantity > $product->limit_purchases_per_customer_limit) {
                Cart::add($product->id, $product->name, $product->limit_purchases_per_customer_limit, $productPrice, $options)->associate(Product::class);

                ShoppingCart::removeInvalidItems();

                return redirect()->back()->with('error', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $product->limit_purchases_per_customer_limit,
                ]))->withInput();
            }

            Cart::add($product->id, $product->name, $quantity, $productPrice, $options)->associate(Product::class);
        }

        $redirectChoice = Customsetting::get('add_to_cart_redirect_to', Sites::getActive(), 'same');
        if ($redirectChoice == 'same') {
            $redirectUrl = url()->previous();
        } elseif ($redirectChoice == 'cart') {
            $redirectUrl = ShoppingCart::getCartUrl();
        } elseif ($redirectChoice == 'checkout') {
            $redirectUrl = ShoppingCart::getCheckoutUrl();
        }

        ShoppingCart::removeInvalidItems();

        return redirect($redirectUrl)->with('success', Translation::get('product-added-to-cart', 'cart', 'The product has been added to your cart'));
    }

    public function updateToCart(Request $request, $rowId)
    {
        $quantity = $request->qty;
        if (! is_numeric($quantity)) {
            $quantity = 1;
        }

        if (! $quantity) {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                Cart::remove($rowId);
            }

            ShoppingCart::removeInvalidItems();

            return redirect()->back()->with('success', Translation::get('product-removed-from-cart', 'cart', 'The product has been removed from your cart'));
        } else {
            if (ShoppingCart::hasCartitemByRowId($rowId)) {
                $cartItem = Cart::get($rowId);
                Cart::update($rowId, ($cartItem->qty - $cartItem->qty + $quantity));
            }

            ShoppingCart::removeInvalidItems();

            return redirect()->back()->with('success', Translation::get('product-updated-to-cart', 'cart', 'The product has been updated to your cart'));
        }
    }

    public function removeFromCart(Request $request, $rowId)
    {
        if (ShoppingCart::hasCartitemByRowId($rowId)) {
            Cart::remove($rowId);
        }

        ShoppingCart::removeInvalidItems();

        return redirect()->back()->with('success', Translation::get('product-removed-from-cart', 'cart', 'The product has been removed from your cart'));
    }

    public function downloadInvoice(Request $request, $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        $hasAccessToOrder = false;

        if ($order) {
            $hasAccessToOrder = true;
        }

        if (! $hasAccessToOrder) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return response()->download(storage_path('app/public/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf'));
    }

    public function downloadPackingSlip(Request $request, $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        $hasAccessToOrder = false;

        if ($order) {
            $hasAccessToOrder = true;
        }

        if (! $hasAccessToOrder) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return response()->download(storage_path('app/public/packing-slips/packing-slip-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf'));
    }
}
