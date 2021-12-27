<?php

namespace Qubiqx\QcommerceEcommerceCore\Controllers\Frontend;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Artesaos\SEOTools\Facades\SEOTools;
use Gloudemans\Shoppingcart\Facades\Cart;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Controllers\Frontend\FrontendController;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\Translation;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Qubiqx\QcommerceEcommerceCore\Models\DiscountCode;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;
use Qubiqx\QcommerceEcommerceCore\Models\Product;
use Qubiqx\QcommerceEcommerceCore\Models\ProductExtraOption;

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
        if (!$request->discount_code) {
            session(['discountCode' => '']);

            ShoppingCart::removeInvalidItems();
            return redirect()->back();
        }

        $discountCode = DiscountCode::usable()->where('code', $request->discount_code)->first();

        if (!$discountCode || !$discountCode->isValidForCart()) {
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
        if (!$quantity || !is_numeric($quantity)) {
            $quantity = 1;
        }

        $cartItems = ShoppingCart::cartItems();
        $cartUpdated = false;
        $productPrice = $product->currentPrice;
        $options = [];
        foreach ($product->allProductExtras() as $productExtra) {
            if ($productExtra->type == 'single') {
                $productValue = $request['product-extra-' . $productExtra->id];
                if ($productExtra->required && !$productValue) {
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
                    if ($productExtra->required && !$productOptionValue) {
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
                        'quantity' => $product->limit_purchases_per_customer_limit
                    ]))->withInput();
                }

                Cart::update($cartItem->rowId, $newQuantity);
                $cartUpdated = true;
            }
        }

        if (!$cartUpdated) {
            if ($product->limit_purchases_per_customer && $quantity > $product->limit_purchases_per_customer_limit) {
                Cart::add($product->id, $product->name, $product->limit_purchases_per_customer_limit, $productPrice, $options)->associate(Product::class);

                ShoppingCart::removeInvalidItems();

                return redirect()->back()->with('error', Translation::get('product-only-x-purchase-per-customer', 'cart', 'You can only purchase :quantity: of this product', 'text', [
                    'quantity' => $product->limit_purchases_per_customer_limit
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
        if (!is_numeric($quantity)) {
            $quantity = 1;
        }

        if (!$quantity) {
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

    //Todo: move to own TransactionController
//    public function startTransaction(StartTransactionRequest $request)
//    {
//        ShoppingCart::removeInvalidItems();
//
//        $cartItems = ShoppingCart::cartItems();
//
//        if (!$cartItems) {
//            return redirect()->back()->with('error', Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'))->withInput();
//        }
//
//        $paymentMethods = ShoppingCart::getPaymentMethods();
//        $paymentMethod = '';
//        foreach ($paymentMethods as $thisPaymentMethod) {
//            if ($thisPaymentMethod['id'] == $request->payment_method) {
//                $paymentMethod = $thisPaymentMethod;
//            }
//        }
//
//        if (!$paymentMethod && (!PayNL::isConnected() && !Mollie::isConnected())) {
//            return redirect()->back()->with('error', Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid payment method'))->withInput();
//        }
//
//        $shippingMethods = ShoppingCart::getAvailableShippingMethods($request->country);
//        $shippingMethod = '';
//        foreach ($shippingMethods as $thisShippingMethod) {
//            if ($thisShippingMethod['id'] == $request->shipping_method) {
//                $shippingMethod = $thisShippingMethod;
//            }
//        }
//
//        if (!$shippingMethod) {
//            return redirect()->back()->with('error', Translation::get('no-valid-shipping-method-chosen', 'cart', 'You did not choose a valid shipping method'))->withInput();
//        }
//
//        $depositAmount = ShoppingCart::depositAmount(false, true, $shippingMethod->id, $paymentMethod['id'] ?? null);
//        if ($depositAmount > 0.00) {
//            $request->validate([
//                'deposit_payment_method' => 'required'
//            ]);
//
//            $depositPaymentMethod = '';
//            foreach ($paymentMethods as $thisPaymentMethod) {
//                if ($thisPaymentMethod['id'] == $request->deposit_payment_method) {
//                    $depositPaymentMethod = $thisPaymentMethod;
//                }
//            }
//
//            if (!$depositPaymentMethod) {
//                return redirect()->back()->with('error', Translation::get('no-valid-deposit-payment-method-chosen', 'cart', 'You did not choose a valid payment method for the deposit'))->withInput();
//            }
//        }
//
//        $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();
//
//        if (!$discountCode) {
//            session(['discountCode' => '']);
//            $discountCode = '';
//        } elseif ($discountCode && !$discountCode->isValidForCart($request->email)) {
//            session(['discountCode' => '']);
//
//            return redirect()->back()->with('error', Translation::get('discount-code-invalid', 'cart', 'The discount code you choose is invalid'))->withInput();
//        }
//
//        if (Customsetting::get('checkout_account') != 'disabled' && Auth::guest() && $request->password) {
//            if (User::where('email', $request->email)->count()) {
//                return redirect()->back()->with('error', Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'))->withInput();
//            }
//
//            $user = new User();
//            $user->first_name = $request->first_name;
//            $user->last_name = $request->last_name;
//            $user->email = $request->email;
//            $user->password = Hash::make($request->password);
//            $user->save();
//
//            Auth::login($user, $request->remember_me);
//        }
//
//        $order = new Order();
//        $order->first_name = $request->first_name;
//        $order->gender = $request->gender;
//        $order->date_of_birth = $request->date_of_birth ? Carbon::parse($request->date_of_birth) : null;
//        $order->last_name = $request->last_name;
//        $order->email = $request->email;
//        $order->phone_number = $request->phone_number;
//        $order->street = $request->street;
//        $order->house_nr = $request->house_nr;
//        $order->zip_code = $request->zip_code;
//        $order->city = $request->city;
//        $order->country = $request->country;
//        $order->marketing = $request->marketing ? 1 : 0;
//        $order->company_name = $request->company_name;
//        $order->btw_id = $request->btw_id;
//        $order->note = $request->note;
//        $order->invoice_street = $request->invoice_street;
//        $order->invoice_house_nr = $request->invoice_house_nr;
//        $order->invoice_zip_code = $request->invoice_zip_code;
//        $order->invoice_city = $request->invoice_city;
//        $order->invoice_country = $request->invoice_country;
//        $order->invoice_id = 'PROFORMA';
//
//        $subTotal = ShoppingCart::subtotal(false, $shippingMethod->id, $paymentMethod['id'] ?? '');
//        $discount = ShoppingCart::totalDiscount();
//        $btw = ShoppingCart::btw(false, true, $shippingMethod->id, $paymentMethod['id'] ?? '');
//        $total = ShoppingCart::total(false, true, $shippingMethod->id, $paymentMethod['id'] ?? '');
//        $shippingCosts = 0;
//        $paymentCosts = 0;
//
//        if ($shippingMethod->costs > 0) {
//            $shippingCosts = $shippingMethod->costs;
//        }
//
//        if ($paymentMethod && isset($paymentMethod['extra_costs']) && $paymentMethod['extra_costs'] > 0) {
//            $paymentCosts = $paymentMethod['extra_costs'];
//        }
//
//        $order->total = $total;
//        $order->subtotal = $subTotal;
//        $order->btw = $btw;
//        $order->discount = $discount;
//        $order->status = 'pending';
//        $gaUserId = preg_replace("/^.+\.(.+?\..+?)$/", '\\1', @$_COOKIE['_ga']);
//        $order->ga_user_id = $gaUserId;
//
//        if ($discountCode) {
//            $order->discount_code_id = $discountCode->id;
//        }
//
//        $order->shipping_method_id = $shippingMethod['id'];
//
//        if (Auth::check()) {
//            $order->user_id = Auth::user()->id;
//        }
//
//        $order->save();
//
//        $orderContainsPreOrders = false;
//        foreach ($cartItems as $cartItem) {
//            $orderProduct = new OrderProduct();
//            $orderProduct->quantity = $cartItem->qty;
//            $orderProduct->product_id = $cartItem->model->id;
//            $orderProduct->order_id = $order->id;
//            $orderProduct->name = $cartItem->model->name;
//            $orderProduct->sku = $cartItem->model->sku;
//            if ($discountCode) {
//                $discountedPrice = $discountCode->getDiscountedPriceForProduct($cartItem);
//                $orderProduct->price = $discountedPrice;
//                $orderProduct->discount = ($cartItem->price * $orderProduct->quantity) - $discountedPrice;
//            } else {
//                $orderProduct->price = $cartItem->price * $orderProduct->quantity;
//                $orderProduct->discount = 0;
//            }
//            $productExtras = [];
//            foreach ($cartItem->options as $optionId => $option) {
//                $productExtras[] = [
//                    'id' => $optionId,
//                    'name' => $option['name'],
//                    'value' => $option['value'],
//                    'price' => ProductExtraOption::find($optionId)->price,
//                ];
//            }
//            $orderProduct->product_extras = json_encode($productExtras);
//
//            if ($cartItem->model->isPreorderable() && $cartItem->model->stock < $cartItem->qty) {
//                $orderProduct->is_pre_order = true;
//                $orderProduct->pre_order_restocked_date = $cartItem->model->expected_in_stock_date;
//                $orderContainsPreOrders = true;
//            }
//
//            $orderProduct->save();
//        }
//
//        if ($paymentCosts) {
//            $orderProduct = new OrderProduct();
//            $orderProduct->quantity = 1;
//            $orderProduct->product_id = null;
//            $orderProduct->order_id = $order->id;
//            $orderProduct->name = $paymentMethod['name'];
//            $orderProduct->price = $paymentCosts;
//            if ($order->paymentMethod) {
//                $orderProduct->btw = ShoppingCart::vatForPaymentMethod($paymentMethod['id']);
//            }
//            $orderProduct->discount = 0;
//            $orderProduct->product_extras = json_encode([]);
//            $orderProduct->sku = 'payment_costs';
//            $orderProduct->save();
//        }
//
//        if ($shippingCosts) {
//            $orderProduct = new OrderProduct();
//            $orderProduct->quantity = 1;
//            $orderProduct->product_id = null;
//            $orderProduct->order_id = $order->id;
//            $orderProduct->name = $order->shippingMethod->name;
//            $orderProduct->price = $shippingCosts;
//            $orderProduct->btw = ShoppingCart::vatForShippingMethod($order->shippingMethod->id, false, true);
//            $orderProduct->vat_rate = ShoppingCart::vatRateForShippingMethod($order->shippingMethod->id);
//            $orderProduct->discount = ShoppingCart::vatForShippingMethod($order->shippingMethod->id, false, false) - $orderProduct->btw;
//            $orderProduct->product_extras = json_encode([]);
//            $orderProduct->sku = 'shipping_costs';
//            $orderProduct->save();
//        }
//
//        if ($orderContainsPreOrders) {
//            $order->contains_pre_orders = true;
//            $order->save();
//        }
//
//        $orderPayment = new OrderPayment();
//        $orderPayment->amount = $order->total;
//        $orderPayment->order_id = $order->id;
//        if ($paymentMethod) {
//            $psp = $paymentMethod['system'];
//        } else {
//            if (PayNL::isConnected()) {
//                $psp = 'paynl';
//            } elseif (Mollie::isConnected()) {
//                $psp = 'mollie';
//            }
//        }
//        $orderPayment->psp = $psp;
//
//        if (!$paymentMethod) {
//            $orderPayment->payment_method = $psp;
//        } else if ($orderPayment->psp == 'own') {
//            $orderPayment->payment_method_id = $paymentMethod['id'];
//
//            if ($depositAmount > 0.00) {
//                $orderPayment->amount = $depositAmount;
//                $orderPayment->psp = $depositPaymentMethod['system'];
//                $orderPayment->psp_payment_method_id = $depositPaymentMethod['id'];
//
//                $order->has_deposit = true;
//                $order->save();
//            } else {
//                $orderPayment->amount = 0;
//                $orderPayment->status = 'paid';
//            }
//        } else {
//            $orderPayment->payment_method = $paymentMethod['name'];
//            $orderPayment->psp_payment_method_id = $paymentMethod['id'];
//        }
//
//        $orderPayment->save();
//        $orderPayment->refresh();
//
//        $orderLog = new OrderLog();
//        $orderLog->order_id = $order->id;
//        $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
//        $orderLog->tag = 'order.created';
//        $orderLog->save();
//
//        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
//            $newPaymentStatus = 'waiting_for_confirmation';
//            $order->changeStatus($newPaymentStatus);
//
//            return redirect(url(route('qcommerce.frontend.checkout.complete')) . '?paymentId=' . $orderPayment->hash);
//        } elseif ($orderPayment->psp == 'mollie') {
//            $transaction = Mollie::startTransaction($order, $orderPayment);
//
//            return redirect($transaction->getCheckoutUrl(), 303);
//        } elseif ($orderPayment->psp == 'paynl') {
//            $transaction = PayNL::startTransaction($order, $orderPayment);
//
//            return redirect($transaction->getRedirectUrl(), 303);
//        } else {
//            throw new \Exception('Cannot start payment');
//        }
//    }

    public function downloadInvoice(Request $request, $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        $hasAccessToOrder = false;
//        if ($order && ($order->created_at > Carbon::now()->subDay() || (Auth::check() && Auth::user()->role == 'admin') || (Auth::check() && $order->user_id && $order->user_id == Auth::user()->id))) {
//            $hasAccessToOrder = true;
//        }
        if ($order) {
            $hasAccessToOrder = true;
        }

        if (!$hasAccessToOrder) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return response()->download(storage_path('app/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf'));
    }

    public function downloadPackingSlip(Request $request, $orderHash)
    {
        $order = Order::where('hash', $orderHash)->first();

        $hasAccessToOrder = false;
//        if ($order && ($order->created_at > Carbon::now()->subDay() || (Auth::check() && Auth::user()->role == 'admin') || (Auth::check() && $order->user_id && $order->user_id == Auth::user()->id))) {
//            $hasAccessToOrder = true;
//        }
        if ($order) {
            $hasAccessToOrder = true;
        }

        if (!$hasAccessToOrder) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        return response()->download(storage_path('app/packing-slips/packing-slip-' . ($order->invoice_id ?: $order->id) . '-' . $order->hash . '.pdf'));
    }

    //Todo: move to TransactionController
//    public function complete(Request $request)
//    {
//        $paymentId = $request->paymentId;
//
//        if (!$paymentId) {
//            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
//        }
//
//        $orderPayment = OrderPayment::where('psp_id', $paymentId)->orWhere('hash', $paymentId)->first();
//
//        if (!$orderPayment) {
//            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
//        }
//
//        $order = $orderPayment->order;
//
//        $hasAccessToOrder = false;
//
//        if ($order) {
//            $hasAccessToOrder = true;
//        }
//
//        if (!$hasAccessToOrder) {
//            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
//        }
//
//        if ($orderPayment->psp == 'own') {
//        } elseif ($orderPayment->psp == 'mollie') {
//            $newStatus = Mollie::getOrderStatus($orderPayment);
//            $newPaymentStatus = $orderPayment->changeStatus($newStatus);
//        } elseif ($orderPayment->psp == 'paynl') {
//            $newStatus = PayNL::getOrderStatus($orderPayment);
//            $newPaymentStatus = $orderPayment->changeStatus($newStatus);
//        }
//
//        if (isset($newPaymentStatus)) {
//            $order->changeStatus($newPaymentStatus);
//            $order->sendGAEcommerceHit();
//        }
//
//        if ($order->status == 'pending') {
//            return redirect('/')->with('error', Translation::get('order-status-pending', 'checkout', 'Your order is still pending'));
//        }
//
//        if ($order->status == 'cancelled') {
//            return redirect('/')->with('error', Translation::get('order-status-cancelled', 'checkout', 'Your order is cancelled'));
//        }
//
//        if (View::exists('qcommerce.checkout.complete')) {
//            SEOTools::setTitle(Translation::get('complete-page-meta-title', 'complete-order', 'Your order'));
//            SEOTools::opengraph()->setUrl(url()->current());
//
//            View::share('order', $order);
//
//            return view('qcommerce.checkout.complete');
//        } else {
//            return $this->pageNotFound();
//        }
//    }

        //Todo: move to TransactionController
//    public function exchange(Request $request)
//    {
//        $paymentId = $request->paymentId;
//        $orderId = $request->id;
//
//        if (!$orderId) {
//            $orderId = $request->orderId;
//        }
//
//        if (!$orderId) {
//            $orderId = $request->order_id;
//        }
//
//        if (!$orderId && !$paymentId) {
//            echo "OrderID = $orderId";
//            echo "PaymentID = $paymentId";
//            return 'order not found';
//        }
//
//        if ($paymentId) {
//            $orderPayment = OrderPayment::where('psp_id', $paymentId)->orWhere('hash', $paymentId)->first();
//        } else {
//            $orderPayment = OrderPayment::where('psp_id', $orderId)->orWhere('hash', $orderId)->first();
//        }
//
//        if (!$orderPayment) {
//            return 'order not found';
//        }
//
//        $order = $orderPayment->order;
//
//        if ($orderPayment->psp == 'own') {
//            $newPaymentStatus = 'waiting_for_confirmation';
//            $order->changeStatus($newPaymentStatus);
//        } elseif ($orderPayment->psp == 'mollie') {
//            $newStatus = Mollie::getOrderStatus($orderPayment);
//            $newPaymentStatus = $orderPayment->changeStatus($newStatus);
//            $order->changeStatus($newPaymentStatus);
//        } elseif ($orderPayment->psp == 'paynl') {
//            $newStatus = PayNL::getOrderStatus($orderPayment);
//            $newPaymentStatus = $orderPayment->changeStatus($newStatus);
//            $order->changeStatus($newPaymentStatus);
//
//            if ($order->status == 'paid') {
//                echo "TRUE| Paid";
//                return;
//            } elseif ($order->status == 'cancelled') {
//                echo "TRUE| Canceled";
//                return;
//            } else {
//                echo "TRUE| Pending";
//                return;
//            }
//        }
//
//        return $order->status;
//    }
}
