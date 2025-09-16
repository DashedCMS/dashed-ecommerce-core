<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Storage;
use Gloudemans\Shoppingcart\Facades\Cart;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedCore\Controllers\Frontend\FrontendController;
use Dashed\DashedEcommerceCore\Livewire\Frontend\Checkout\Checkout;

class CartController extends FrontendController
{
    public function cart()
    {
        if (Customsetting::get('checkout_force_checkout_page')) {
            return redirect()->to(ShoppingCart::getCheckoutUrl());
        }

        ShoppingCart::removeInvalidItems();

        if (View::exists(config('dashed-core.site_theme') . '.cart.cart')) {
            seo()->metaData('metaTitle', Translation::get('cart-page-meta-title', 'cart', 'Cart'));
            seo()->metaData('metaDescription', Translation::get('cart-page-meta-description', 'cart', 'View your shopping cart here'));

            return view('dashed-core::layouts.livewire-master', [
                'livewireComponent' => \Dashed\DashedEcommerceCore\Livewire\Frontend\Cart\Cart::class,
            ]);

            return view(config('dashed-core.site_theme') . '.cart.cart');
        } else {
            return $this->pageNotFound();
        }
    }

    public function checkout()
    {
        ShoppingCart::removeInvalidItems();

        if (View::exists(config('dashed-core.site_theme') . '.checkout.checkout')) {
            seo()->metaData('metaTitle', Translation::get('checkout-page-meta-title', 'cart', 'Pay now'));
            seo()->metaData('metaDescription', Translation::get('checkout-page-meta-description', 'cart', 'Finish your order'));

            return view('dashed-core::layouts.livewire-master', [
                'livewireComponent' => Checkout::class,
            ]);

            return view(config('dashed-core.site_theme') . '.checkout.checkout');
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
                        $productPrice += ($productExtraOption->price / $quantity);
                    } else {
                        $productPrice += $productExtraOption->price;
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

        if (! $hasAccessToOrder || ! $order->downloadInvoiceUrl()) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return Storage::disk('dashed')->download('dashed/invoices/invoice-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf');
    }

    public function downloadPackingSlip(Request $request, $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        $hasAccessToOrder = false;

        if ($order) {
            $hasAccessToOrder = true;
        }

        if (! $hasAccessToOrder || ! $order->downloadPackingslipUrl()) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return Storage::disk('dashed')->download('dashed/packing-slips/packing-slip-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf');
    }
}
