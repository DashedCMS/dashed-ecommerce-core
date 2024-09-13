<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;
use Dashed\DashedCore\Controllers\Frontend\FrontendController;
use Dashed\DashedEcommerceCore\Requests\Frontend\StartTransactionRequest;

class TransactionController extends FrontendController
{
    public function startTransaction(StartTransactionRequest $request)
    {
        ShoppingCart::removeInvalidItems();

        $cartItems = ShoppingCart::cartItems();

        if (! $cartItems) {
            return redirect()->back()->with('error', Translation::get('no-items-in-cart', 'cart', 'You dont have any products in your shopping cart'))->withInput();
        }

        $paymentMethods = ShoppingCart::getPaymentMethods();
        $paymentMethod = '';
        foreach ($paymentMethods as $thisPaymentMethod) {
            if ($thisPaymentMethod['id'] == $request->payment_method) {
                $paymentMethod = $thisPaymentMethod;
            }
        }

        $paymentMethodPresent = (bool)$paymentMethod;
        if (! $paymentMethodPresent) {
            foreach (ecommerce()->builder('paymentServiceProviders') as $psp) {
                if ($psp['class']::isConnected()) {
                    $paymentMethodPresent = true;
                }
            }
            if (! $paymentMethodPresent) {
                return redirect()->back()->with('error', Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid payment method'))->withInput();
            }
        }

        $shippingMethods = ShoppingCart::getAvailableShippingMethods($request->country);
        $shippingMethod = '';
        foreach ($shippingMethods as $thisShippingMethod) {
            if ($thisShippingMethod['id'] == $request->shipping_method) {
                $shippingMethod = $thisShippingMethod;
            }
        }

        if (! $shippingMethod) {
            return redirect()->back()->with('error', Translation::get('no-valid-shipping-method-chosen', 'cart', 'You did not choose a valid shipping method'))->withInput();
        }

        $depositAmount = ShoppingCart::depositAmount(false, true, $shippingMethod->id, $paymentMethod['id'] ?? null);
        if ($depositAmount > 0.00) {
            $request->validate([
                'deposit_payment_method' => 'required',
            ]);

            $depositPaymentMethod = '';
            foreach ($paymentMethods as $thisPaymentMethod) {
                if ($thisPaymentMethod['id'] == $request->deposit_payment_method) {
                    $depositPaymentMethod = $thisPaymentMethod;
                }
            }

            if (! $depositPaymentMethod) {
                return redirect()->back()->with('error', Translation::get('no-valid-deposit-payment-method-chosen', 'cart', 'You did not choose a valid payment method for the deposit'))->withInput();
            }
        }

        $discountCode = DiscountCode::usable()->where('code', session('discountCode'))->first();

        if (! $discountCode) {
            session(['discountCode' => '']);
            $discountCode = '';
        } elseif ($discountCode && ! $discountCode->isValidForCart($request->email)) {
            session(['discountCode' => '']);

            return redirect()->back()->with('error', Translation::get('discount-code-invalid', 'cart', 'The discount code you choose is invalid'))->withInput();
        }

        if (Customsetting::get('checkout_account') != 'disabled' && Auth::guest() && $request->password) {
            if (User::where('email', $request->email)->count()) {
                return redirect()->back()->with('error', Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'))->withInput();
            }

            $user = new User();
            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();

            Auth::login($user, $request->remember_me);
        }

        $order = new Order();
        $order->first_name = $request->first_name;
        $order->gender = $request->gender;
        $order->date_of_birth = $request->date_of_birth ? Carbon::parse($request->date_of_birth) : null;
        $order->last_name = $request->last_name;
        $order->email = $request->email;
        $order->phone_number = $request->phone_number;
        $order->street = $request->street;
        $order->house_nr = $request->house_nr;
        $order->zip_code = $request->zip_code;
        $order->city = $request->city;
        $order->country = $request->country;
        $order->marketing = $request->marketing ? 1 : 0;
        $order->company_name = $request->company_name;
        $order->btw_id = $request->btw_id;
        $order->note = $request->note;
        $order->invoice_street = $request->invoice_street;
        $order->invoice_house_nr = $request->invoice_house_nr;
        $order->invoice_zip_code = $request->invoice_zip_code;
        $order->invoice_city = $request->invoice_city;
        $order->invoice_country = $request->invoice_country;
        $order->invoice_id = 'PROFORMA';

        $subTotal = ShoppingCart::subtotal(false, $shippingMethod->id, $paymentMethod['id'] ?? '');
        $discount = ShoppingCart::totalDiscount();
        $btw = ShoppingCart::btw(false, true, $shippingMethod->id, $paymentMethod['id'] ?? '');
        $total = ShoppingCart::total(false, true, $shippingMethod->id, $paymentMethod['id'] ?? '');
        $shippingCosts = 0;
        $paymentCosts = 0;

        if ($shippingMethod->costs > 0) {
            $shippingCosts = $shippingMethod->costs;
        }

        if ($paymentMethod && isset($paymentMethod['extra_costs']) && $paymentMethod['extra_costs'] > 0) {
            $paymentCosts = $paymentMethod['extra_costs'];
        }

        $order->total = $total;
        $order->subtotal = $subTotal;
        $order->btw = $btw;
        $order->discount = $discount;
        $order->status = 'pending';
        $gaUserId = preg_replace("/^.+\.(.+?\..+?)$/", '\\1', @$_COOKIE['_ga']);
        $order->ga_user_id = $gaUserId;

        if ($discountCode) {
            $order->discount_code_id = $discountCode->id;
        }

        $order->shipping_method_id = $shippingMethod['id'];

        if (Auth::check()) {
            $order->user_id = Auth::user()->id;
        }

        $order->save();

        $orderContainsPreOrders = false;
        foreach ($cartItems as $cartItem) {
            $isBundleItemWithIndividualPricing = false;
            if ($cartItem->model->is_bundle && $cartItem->model->use_bundle_product_price) {
                $isBundleItemWithIndividualPricing = true;
            }

            $orderProduct = new OrderProduct();
            $orderProduct->quantity = $cartItem->qty;
            $orderProduct->product_id = $cartItem->model->id;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $cartItem->model->name;
            $orderProduct->sku = $cartItem->model->sku;
            //            if ($discountCode) {
            //                $discountedPrice = $discountCode->getDiscountedPriceForProduct($cartItem->model, $cartItem->qty);
            //                $orderProduct->price = $discountedPrice;
            //                $orderProduct->discount = ($cartItem->model->currentPrice * $orderProduct->quantity) - $discountedPrice;
            //            } else {
            $orderProduct->price = $cartItem->model->getShoppingCartItemPrice($cartItem, $discountCode ?? null);
            $orderProduct->discount = $cartItem->model->getShoppingCartItemPrice($cartItem) - $orderProduct->price;
            //            }
            $productExtras = [];
            foreach ($cartItem->options as $optionId => $option) {
                $productExtras[] = [
                    'id' => $optionId,
                    'name' => $option['name'],
                    'value' => $option['value'],
                    'price' => ProductExtraOption::find($optionId)->price,
                ];
            }
            $orderProduct->product_extras = $productExtras;

            if ($cartItem->model->isPreorderable() && $cartItem->model->stock < $cartItem->qty) {
                $orderProduct->is_pre_order = true;
                $orderProduct->pre_order_restocked_date = $cartItem->model->expected_in_stock_date;
                $orderContainsPreOrders = true;
            }

            $orderProduct->save();

            foreach ($cartItem->model->bundleProducts as $bundleProduct) {

                $orderProduct = new OrderProduct();
                $orderProduct->quantity = $cartItem->qty;
                $orderProduct->product_id = $bundleProduct->id;
                $orderProduct->order_id = $order->id;
                $orderProduct->name = $bundleProduct->name;
                $orderProduct->sku = $bundleProduct->sku;

                //                if ($isBundleItemWithIndividualPricing) {
                //                    if ($discountCode) {
                //                        $discountedPrice = $discountCode->getDiscountedPriceForProduct($bundleProduct, $cartItem->qty);
                //                        $orderProduct->price = $discountedPrice;
                //                        $orderProduct->discount = ($bundleProduct->currentPrice * $orderProduct->quantity) - $discountedPrice;
                //                    } else {
                //                        $orderProduct->price = $bundleProduct->currentPrice * $orderProduct->quantity;
                //                        $orderProduct->discount = 0;
                //                    }
                //                } else {
                $orderProduct->price = 0;
                $orderProduct->discount = 0;
                //                }

                if ($bundleProduct->isPreorderable() && $bundleProduct->stock < $cartItem->qty) {
                    $orderProduct->is_pre_order = true;
                    $orderProduct->pre_order_restocked_date = $bundleProduct->expected_in_stock_date;
                    $orderContainsPreOrders = true;
                }

                $orderProduct->save();
            }
        }

        if ($paymentCosts) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $paymentMethod['name'];
            $orderProduct->price = $paymentCosts;
            if ($order->paymentMethod) {
                $orderProduct->btw = ShoppingCart::vatForPaymentMethod($paymentMethod['id']);
            }
            $orderProduct->discount = 0;
            $orderProduct->product_extras = [];
            $orderProduct->sku = 'payment_costs';
            $orderProduct->save();
        }

        if ($shippingCosts) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = 1;
            $orderProduct->product_id = null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $order->shippingMethod->name;
            $orderProduct->price = $shippingCosts;
            $orderProduct->btw = ShoppingCart::vatForShippingMethod($order->shippingMethod->id, false, true);
            $orderProduct->vat_rate = ShoppingCart::vatRateForShippingMethod($order->shippingMethod->id);
            $orderProduct->discount = ShoppingCart::vatForShippingMethod($order->shippingMethod->id, false, false) - $orderProduct->btw;
            $orderProduct->product_extras = [];
            $orderProduct->sku = 'shipping_costs';
            $orderProduct->save();
        }

        if ($orderContainsPreOrders) {
            $order->contains_pre_orders = true;
            $order->save();
        }

        $orderPayment = new OrderPayment();
        $orderPayment->amount = $order->total;
        $orderPayment->order_id = $order->id;
        if ($paymentMethod) {
            $psp = $paymentMethod['psp'];
        } else {
            foreach (ecommerce()->builder('paymentServiceProviders') as $pspId => $ecommercePSP) {
                if ($ecommercePSP['class']::isConnected()) {
                    $psp = $pspId;
                }
            }
        }

        $orderPayment->psp = $psp;

        if (! $paymentMethod) {
            $orderPayment->payment_method = $psp;
        } elseif ($orderPayment->psp == 'own') {
            $orderPayment->payment_method_id = $paymentMethod['id'];

            if ($depositAmount > 0.00) {
                $orderPayment->amount = $depositAmount;
                $orderPayment->psp = $depositPaymentMethod['psp'];
                $orderPayment->payment_method_id = $depositPaymentMethod['id'];

                $order->has_deposit = true;
                $order->save();
            } else {
                $orderPayment->amount = 0;
                $orderPayment->status = 'paid';
            }
        } else {
            $orderPayment->payment_method = $paymentMethod['name'];
            $orderPayment->payment_method_id = $paymentMethod['id'];
        }

        $orderPayment->save();
        $orderPayment->refresh();

        $orderLog = new OrderLog();
        $orderLog->order_id = $order->id;
        $orderLog->user_id = Auth::check() ? Auth::user()->id : null;
        $orderLog->tag = 'order.created';
        $orderLog->save();

        if ($orderPayment->psp == 'own' && $orderPayment->status == 'paid') {
            $newPaymentStatus = 'waiting_for_confirmation';
            $order->changeStatus($newPaymentStatus);

            return redirect(url(route('dashed.frontend.checkout.complete')) . '?paymentId=' . $orderPayment->hash);
        } else {
            try {
                $transaction = ecommerce()->builder('paymentServiceProviders')[$orderPayment->psp]['class']::startTransaction($orderPayment);
            } catch (\Exception $exception) {
                return redirect()->back()->with('error', Translation::get('failed-to-start-payment-try-again', 'cart', 'The payment could not be started, please try again'))->withInput();

                throw new \Exception('Cannot start payment: ' . $exception->getMessage());
            }

            return redirect($transaction['redirectUrl'], 303);
        }
    }

    public function complete(Request $request)
    {
        $possibleIdValues = [
            'orderId',
            'order_id',
            'paymentId',
            'id',
            'transactionid',
        ];

        $orderPayment = null;

        foreach ($possibleIdValues as $possibleIdValue) {
            if (! $orderPayment) {
                $paymentId = $request->get($possibleIdValue);
                if ($paymentId) {
                    $orderPayment = OrderPayment::where('psp_id', $paymentId)->orWhere('hash', $paymentId)->first();
                }
            }
        }

        if (! $orderPayment) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        $order = $orderPayment->order;

        $hasAccessToOrder = false;

        if ($order) {
            $hasAccessToOrder = true;
        }

        if (! $hasAccessToOrder) {
            return redirect('/')->with('error', Translation::get('order-not-found', 'checkout', 'The order could not be found'));
        }

        $lock = Cache::lock('order.check-payment.' . $order->id, 10);

        try {
            if ($lock->get()) {
                foreach (ecommerce()->builder('paymentServiceProviders') ?: [] as $pspId => $psp) {
                    if ($orderPayment->psp == $pspId) {
                        $newStatus = $psp['class']::getOrderStatus($orderPayment);
                        $newPaymentStatus = $orderPayment->changeStatus($newStatus);
                    }
                }

                if (isset($newPaymentStatus)) {
                    $order->changeStatus($newPaymentStatus);
                    $order->sendGAEcommerceHit();
                }
            }
        } catch (LockTimeoutException $e) {
            return 'timeout exception';
        } finally {
            $lock->release();
        }

        if ($order->status == 'pending') {
            return redirect('/')->with('error', Translation::get('order-status-pending', 'checkout', 'Your order is still pending'));
        }

        if ($order->status == 'cancelled') {
            return redirect('/')->with('error', Translation::get('order-status-cancelled', 'checkout', 'Your order is cancelled'));
        }

        if (view()->exists('dashed.checkout.complete') || view()->exists('dashed.orders.view-order')) {
            seo()->metaData('metaTitle', Translation::get('complete-page-meta-title', 'complete-order', 'Your order'));

            View::share('order', $order);

            return view()->exists('dashed.orders.view-order') ? view(Customsetting::get('site_theme', null, 'dashed') . '.orders.view-order') : view(Customsetting::get('site_theme', null, 'dashed') . '.checkout.complete');
        } else {
            return $this->pageNotFound();
        }
    }

    public function exchange(Request $request)
    {
        $possibleIdValues = [
            'orderId',
            'order_id',
            'paymentId',
            'id',
            'transactionid',
        ];

        $orderPayment = null;

        foreach ($possibleIdValues as $possibleIdValue) {
            if (! $orderPayment) {
                $paymentId = $request->get($possibleIdValue);
                if ($paymentId) {
                    $orderPayment = OrderPayment::where('psp_id', $paymentId)->orWhere('hash', $paymentId)->first();
                }
            }
        }

        if (! $orderPayment) {
            return 'order not found';
        }

        $order = $orderPayment->order;

        $lock = Cache::lock('order.check-payment.' . $order->id, 10);

        try {
            if ($lock->get()) {
                if ($orderPayment->psp == 'own') {
                    $newPaymentStatus = 'waiting_for_confirmation';
                    $order->changeStatus($newPaymentStatus);
                } else {
                    foreach (ecommerce()->builder('paymentServiceProviders') ?: [] as $pspId => $psp) {
                        if ($orderPayment->psp == $pspId) {
                            $newStatus = $psp['class']::getOrderStatus($orderPayment);
                            $newPaymentStatus = $orderPayment->changeStatus($newStatus);
                            $order->changeStatus($newPaymentStatus);
                        }
                    }
                }
            }
        } catch (LockTimeoutException $e) {
            $orderLog = new OrderLog();
            $orderLog->order_id = $event->order->id;
            $orderLog->user_id = null;
            $orderLog->tag = 'system.note.created';
            $orderLog->note = 'Order status could not be updated due to a lock timeout exception';
            $orderLog->save();
            return 'timeout exception';
        } finally {
            $lock->release();
        }

        if ($order->status == 'paid') {
            echo "TRUE| Paid";
        } elseif ($order->status == 'cancelled') {
            echo "TRUE| Canceled";
        } else {
            echo "TRUE| Pending";
        }

        return $order->status;
    }
}
