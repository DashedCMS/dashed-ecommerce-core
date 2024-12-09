<?php

namespace Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale;

use Carbon\Carbon;
use Paynl\Payment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Dashed\DashedCore\Models\User;
use App\Http\Controllers\Controller;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Classes\POSHelper;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\PinTerminal;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

class PointOfSaleApiController extends Controller
{
    public function openCashRegister(Request $request)
    {
        $response = PinTerminal::openCashRegister();

        return response()->json($response, $response['success'] ? 200 : 400);
    }

    public function initialize(Request $request)
    {
        $data = $request->all();

        $userId = $data['userId'] ?? null;

        $posCart = POSCart::where('user_id', $userId)->where('status', 'active')->first();
        if ($posCart) {
            $posIdentifier = $posCart->identifier;
            $products = $posCart->products;
        } else {
            $posIdentifier = uniqid();
            $posCart = new POSCart();
            $posCart->identifier = $posIdentifier;
            $posCart->user_id = $userId;
            $posCart->status = 'active';
            $posCart->save();
        }

        foreach ($products ?? [] as $productKey => &$product) {
            if (! isset($product['customProduct']) || $product['customProduct'] == false) {
                $product = Product::find($product['id'] ?? 0);
                if (! $product) {
                    unset($products[$productKey]);

                    continue;
                }
                $product['image'] = $product->firstImage;
            }
        }

        return response()
            ->json([
                'posIdentifier' => $posIdentifier ?? null,
                'products' => $products ?? [],
                'lastOrder' => Order::where('order_origin', 'pos')->latest()->first(),
                'success' => true,
            ]);
    }

    public function retrieveCart(Request $request): JsonResponse
    {
        $data = $request->all();

        $cartInstance = $data['cartInstance'] ?? '';
        $posIdentifier = $data['posIdentifier'] ?? '';
        $discountCode = $data['discountCode'] ?? '';

        return response()
            ->json($this->updateCart(
                $cartInstance,
                $posIdentifier,
                $discountCode
            ));
    }

    public function updateCart(string $cartInstance, string $posIdentifier, ?string $discountCode = null): array
    {
        ShoppingCart::setInstance($cartInstance);
        ShoppingCart::emptyMyCart();

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $discountCode = $discountCode ?? $posCart->discount_code;

        $products = $posCart->products ?? [];

        foreach ($products ?: [] as $chosenProduct) {
            $product = Product::find($chosenProduct['id']);
            if (($chosenProduct['quantity'] ?? 0) > 0) {
                $productPrice = ($chosenProduct['customProduct'] ?? false) ? $chosenProduct['singlePrice'] : $product->getOriginal('price');
                $options = [];
                foreach ($chosenProduct['extra'] ?? [] as $productExtraId => $productExtraOptionId) {
                    if ($productExtraOptionId) {
                        $thisProductExtra = ProductExtra::find($productExtraId);
                        $thisOption = ProductExtraOption::find($productExtraOptionId);
                        if ($thisOption->calculate_only_1_quantity) {
                            $productPrice += ($thisOption->price / $this->products[$product->id]['quantity']);
                        } else {
                            $productPrice += $thisOption->price;
                        }
                        $options[$thisOption->id] = [
                            'name' => $thisProductExtra->name,
                            'value' => $thisOption->value,
                        ];
                    }
                }

                $options['options'] = $options;

                if ($product->id ?? false) {
                    \Cart::instance($cartInstance)->add($product->id, $product->name ?? $chosenProduct['name'], $chosenProduct['quantity'], $productPrice, $options)
                        ->associate(Product::class);
                } else {
                    $options['customProduct'] = true;
                    $options['vat_rate'] = $chosenProduct['vat_rate'];
                    $options['singlePrice'] = $chosenProduct['singlePrice'];
                    \Cart::instance($cartInstance)
                        ->add($chosenProduct['customId'], $product->name ?? $chosenProduct['name'], $chosenProduct['quantity'], $productPrice, $options);
                }
            }
        }

        if (! $discountCode) {
            session(['discountCode' => '']);
            $activeDiscountCode = null;
        } else {
            $discountCode = DiscountCode::usable()->where('code', $discountCode)->first();
            if (! $discountCode || ! $discountCode->isValidForCart()) {
                session(['discountCode' => '']);
                $activeDiscountCode = null;
            } else {
                session(['discountCode' => $discountCode->code]);

                if (! isset($activeDiscountCode) || $activeDiscountCode != $discountCode->code) {
                    $activeDiscountCode = $discountCode->code;
                }

            }
        }

        $posCart->discount_code = $activeDiscountCode ?? null;
        $posCart->save();

        $checkoutData = ShoppingCart::getCheckoutData($shippingMethodId ?? null, $paymentMethodId ?? null);


        $discount = $checkoutData['discountFormatted'];
        $vat = $checkoutData['btwFormatted'];
        $vatPercentages = $checkoutData['btwPercentages'];
        foreach ($vatPercentages as $key => $value) {
            $vatPercentages[$key] = CurrencyHelper::formatPrice($value);
        }
        $subTotal = $checkoutData['subTotalFormatted'];
        $total = $checkoutData['totalFormatted'];
        $totalUnformatted = $checkoutData['total'];

        $paymentMethods = ShoppingCart::getPaymentMethods('pos');

        foreach ($paymentMethods as &$paymentMethod) {
            $paymentMethod['image'] = $paymentMethod['image'] ? (mediaHelper()->getSingleMedia($paymentMethod['image'], ['widen' => 300])->url ?? '') : '';
        }

        return [
            'products' => $products ?? [],
            'discountCode' => $discountCode ?? null,
            'activeDiscountCode' => $activeDiscountCode ?? null,
            'discount' => $discount ?? null,
            'vat' => $vat ?? null,
            'vatPercentages' => $vatPercentages ?? null,
            'subTotal' => $subTotal ?? null,
            'total' => $total ?? null,
            'totalUnformatted' => $totalUnformatted ?? null,
            'paymentMethods' => $paymentMethods ?? [],
            'success' => true,
        ];
    }

    public function printReceipt(Request $request)
    {
        $data = $request->all();

        $orderId = $data['orderId'] ?? null;
        $isCopy = $data['isCopy'] ?? false;

        $order = Order::find($orderId);

        if (! $order) {
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Bestelling niet gevonden',
                ], 404);
        }

        $order->printReceipt($isCopy);

        return response()
            ->json([
                'products' => $products ?? [],
                'discountCode' => $discountCode ?? null,
                'success' => true,
            ]);
    }

    public function searchProducts(Request $request)
    {
        $data = $request->all();

        $search = $data['search'] ?? null;

        $products = Product::handOrderShowable()
            ->search($search)
            ->limit(25)
            ->select(['id', 'name', 'images', 'price'])
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->getTranslation('name', app()->getLocale()),
                    'image' => mediaHelper()->getSingleMedia($product->firstImage, ['widen' => 300])->url ?? '',
                    'currentPrice' => $product->currentPrice,
                    'currentPriceFormatted' => CurrencyHelper::formatPrice($product->currentPrice),
                ];
            })
            ->toArray();

        return response()
            ->json([
                'products' => $products ?? [],
                'success' => true,
            ]);
    }

    public function addProduct(Request $request)
    {
        $data = $request->all();

        $productId = $data['productId'] ?? null;
        $productSearchQuery = $data['productSearchQuery'] ?? null;
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $selectedProduct = Product::handOrderShowable()
            ->find($productId);

        if ($selectedProduct) {
            $products = $this->addProductToCart($posCart, $selectedProduct);

            return response()
                ->json([
                    'products' => $products ?? [],
                    'success' => true,
                ]);
        } else {
            return response()
                ->json([
                    'products' => $products ?? [],
                    'message' => 'Product niet gevonden',
                    'success' => false,
                ], 404);
        }
    }

    public function addProductToCart(POSCart $POSCart, Product $selectedProduct): array
    {
        $productAlreadyInCart = false;

        $products = $POSCart->products ?? [];
        foreach ($products as &$product) {
            if ($product['id'] == $selectedProduct['id']) { //Todo: compare options once supported
                $productAlreadyInCart = true;
                $product['quantity']++;
                $product['price'] = $selectedProduct->getOriginal('price') * $product['quantity'];
            }
        }

        if (! $productAlreadyInCart) {
            $products[] = [
                'id' => $selectedProduct['id'],
                'identifier' => Str::random(),
                'name' => $selectedProduct->getTranslation('name', app()->getLocale()),
                'image' => mediaHelper()->getSingleMedia($selectedProduct->firstImage, ['widen' => 300])->url ?? '',
                'quantity' => 1,
                'price' => $selectedProduct->getOriginal('price'),
                'extra' => [],
            ];
        }

        $POSCart->products = $products;
        $POSCart->save();

        return $products;
    }

    public function selectProduct(Request $request)
    {
        $data = $request->all();

        $productSearchQuery = $data['productSearchQuery'] ?? null;
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $selectedProduct = Product::handOrderShowable()
            ->where('name', $productSearchQuery)
            ->orWhere('sku', $productSearchQuery)
            ->orWhere('ean', $productSearchQuery)
            ->first();

        if (! $selectedProduct) {
            return response()
                ->json([
                    'products' => $products ?? [],
                    'message' => 'Product niet gevonden',
                    'success' => false,
                ], 404);
        }

        $products = $this->addProductToCart($posCart, $selectedProduct);

        return response()
            ->json([
                'products' => $products ?? [],
                'success' => true,
            ]);
    }

    public function changeQuantity(Request $request)
    {
        $data = $request->all();

        $productIdentifier = $data['productIdentifier'] ?? null;
        $quantity = $data['quantity'] ?? null;
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $products = $posCart->products ?? [];

        if ($quantity < 1) {
            $products = collect($products)->reject(function ($product) use ($productIdentifier) {
                return $product['identifier'] === $productIdentifier;
            })->values();
        } else {
            foreach ($products as $productKey => &$product) {
                if ($product['identifier'] == $productIdentifier) {
                    $actualProduct = Product::find($product['id']);
                    $product['quantity'] = $quantity;
                    if ($actualProduct) {
                        $product['price'] = $actualProduct->getOriginal('price') * $quantity;
                    } else {
                        $product['price'] = $product['singlePrice'] * $quantity;
                    }
                }
            }
        }

        $posCart->products = $products;
        $posCart->save();

        return response()
            ->json([
                'products' => $products ?? [],
                'success' => true,
            ]);
    }

    public function clearProducts(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $posCart->products = [];
        $posCart->save();

        return response()
            ->json([
                'products' => [],
                'success' => true,
            ]);
    }

    public function removeDiscount(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();
        $posCart->discount_code = '';
        $posCart->save();

        return response()
            ->json([
                'success' => true,
            ]);
    }

    public function selectPaymentMethod(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $cartInstance = $data['cartInstance'] ?? null;
        $orderOrigin = $data['orderOrigin'] ?? null;
        $paymentMethodId = $data['paymentMethodId'] ?? null;
        $userId = $data['userId'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $response = $this->createOrder($cartInstance, $posCart, $paymentMethodId, $orderOrigin, $userId);

        if ($response['success']) {
            $paymentMethod = PaymentMethod::find($paymentMethodId);

            $order = $response['order'];

            $suggestedCashPaymentAmounts = $this->getPaymentOptions($order->total);

            $isPinTerminalPayment = false;
            if ($paymentMethod->pinTerminal) {
                $isPinTerminalPayment = true;
            }

            return response()
                ->json([
                    'success' => true,
                    'order' => $order,
                    'suggestedCashPaymentAmounts' => $suggestedCashPaymentAmounts,
                    'paymentMethod' => [
                        'id' => $paymentMethod->id,
                        'name' => $paymentMethod->name,
                        'image' => mediaHelper()->getSingleMedia($paymentMethod->image, ['widen' => 300])->url ?? '',
                        'isCashPayment' => $paymentMethod->is_cash_payment,
                    ],
                    'isPinTerminalPayment' => $isPinTerminalPayment,
                ]);
        } else {
            return response()
                ->json([
                    'success' => false,
                    'message' => $response['message'],
                ], 500);
        }
    }

    public function createOrder($cartInstance, $posCart, $paymentMethodId, $orderOrigin, $userId): array
    {
        $this->updateCart($cartInstance, $posCart->identifier);
        //        ShoppingCart::setInstance($cartInstance);
        //        \Cart::instance($cartInstance)->content();
        $cartItems = ShoppingCart::cartItems($cartInstance);
        //        ShoppingCart::removeInvalidItems(checkStock: false); //Dont have to check

        $cartItems = ShoppingCart::cartItems($cartInstance);
        $checkoutData = ShoppingCart::getCheckoutData(null, $paymentMethodId);


        if (! count($cartItems)) {
            return [
                'success' => false,
                'message' => Translation::get('no-items-in-cart', 'cart', 'Je hebt geen producten in je winkelwagen'),
            ];
        }

        //        $paymentMethods = ShoppingCart::getPaymentMethods();
        //        $paymentMethod = '';
        //        foreach ($paymentMethods as $thisPaymentMethod) {
        //            if ($thisPaymentMethod['id'] == $this->payment_method_id) {
        //                $paymentMethod = $thisPaymentMethod;
        //            }
        //        }

        //        if (!$paymentMethod) {
        //            Notification::make()
        //                ->title(Translation::get('no-valid-payment-method-chosen', 'cart', 'You did not choose a valid payment method'))
        //                ->danger()
        //                ->send();
        //
        //            return;
        //        }

        //        $shippingMethods = ShoppingCart::getAvailableShippingMethods($this->country);
        //        $shippingMethod = '';
        //        foreach ($shippingMethods as $thisShippingMethod) {
        //            if ($thisShippingMethod['id'] == $this->shipping_method_id) {
        //                $shippingMethod = $thisShippingMethod;
        //            }
        //        }
        //
        //        if (!$shippingMethod && $this->orderOrigin != 'pos') {
        //            Notification::make()
        //                ->title(Translation::get('no-valid-shipping-method-chosen', 'cart', 'You did not choose a valid shipping method'))
        //                ->danger()
        //                ->send();
        //
        //            return [
        //                'success' => false,
        //            ];
        //        }

        if ($posCart->discount_code) {
            $discountCode = DiscountCode::usable()->where('code', $posCart->discount_code)->first();

            if (! $discountCode) {
                session(['discountCode' => '']);
                $discountCode = '';
            } elseif ($discountCode && ! $discountCode->isValidForCart($this->email)) {
                session(['discountCode' => '']);

                $posCart->discount_code = '';
                $posCart->save();

                return [
                    'success' => false,
                    'message' => Translation::get('discount-code-invalid', 'cart', 'De gekozen kortingscode is niet geldig'),
                ];
            }
        }

        //        if (Customsetting::get('checkout_account') != 'disabled' && Auth::guest() && $this->password) {
        //            if (User::where('email', $this->email)->count()) {
        //                Notification::make()
        //                    ->title(Translation::get('email-duplicate-for-user', 'cart', 'The email you chose has already been used to create a account'))
        //                    ->danger()
        //                    ->send();
        //
        //                return [
        //                    'success' => false,
        //                ];
        //            }
        //
        //            $user = new User();
        //            $user->first_name = $this->first_name;
        //            $user->last_name = $this->last_name;
        //            $user->email = $this->email;
        //            $user->password = Hash::make($this->password);
        //            $user->save();
        //        }

        $order = new Order();
        $order->order_origin = $orderOrigin;
        //        $order->first_name = $this->first_name;
        //        $order->last_name = $this->last_name;
        //        $order->email = $this->email;
        //        $order->gender = $this->gender;
        //        $order->date_of_birth = $this->date_of_birth ? Carbon::parse($this->date_of_birth) : null;
        //        $order->phone_number = $this->phone_number;
        //        $order->street = $this->street;
        //        $order->house_nr = $this->house_nr;
        //        $order->zip_code = $this->zip_code;
        //        $order->city = $this->city;
        //        $order->country = $this->country;
        //        $order->marketing = $this->marketing ? 1 : 0;
        //        $order->company_name = $this->company_name;
        //        $order->btw_id = $this->btw_id;
        //        $order->note = $this->note;
        //        $order->invoice_street = $this->invoice_street;
        //        $order->invoice_house_nr = $this->invoice_house_nr;
        //        $order->invoice_zip_code = $this->invoice_zip_code;
        //        $order->invoice_city = $this->invoice_city;
        //        $order->invoice_country = $this->invoice_country;
        $order->invoice_id = 'PROFORMA';

        session(['discountCode' => $posCart->discount_code]);
        $subTotal = ShoppingCart::subtotal(false, $shippingMethod->id ?? null, $paymentMethodId ?? null);
        $discount = ShoppingCart::totalDiscount(false, $posCart->discount_code);
        $btw = ShoppingCart::btw(false, true, $shippingMethod->id ?? null, $paymentMethodId ?? null);
        $btwPercentages = ShoppingCart::btwPercentages(false, true, $shippingMethod->id ?? null, $paymentMethodId ?? null);
        $total = ShoppingCart::total(false, true, $shippingMethod->id ?? null, $paymentMethodId ?? null);
        $shippingCosts = 0;
        $paymentCosts = 0;

        if (($shippingMethod->costs ?? 0) > 0) {
            $shippingCosts = $shippingMethod->costs;
        }

        if (isset($paymentMethod['extra_costs']) && $paymentMethod['extra_costs'] > 0) {
            $paymentCosts = $paymentMethod['extra_costs'];
        }

        $order->total = $total;
        $order->subtotal = $subTotal;
        $order->btw = $btw;
        $order->vat_percentages = $btwPercentages;
        $order->discount = $discount;
        $order->status = 'pending';
        $order->ga_user_id = null;

        if ($discountCode ?? false) {
            $order->discount_code_id = $discountCode->id;
        }

        $order->shipping_method_id = $shippingMethod['id'] ?? null;

        if (isset($user)) {
            $order->user_id = $user->id;
        } else {
            //            if ($this->user_id) {
            //                $order->user_id = $this->user_id;
            //            }
        }

        $order->save();

        $orderContainsPreOrders = false;
        foreach ($cartItems as $cartItem) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = $cartItem->qty;
            $orderProduct->product_id = $cartItem->model->id ?? null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $cartItem->model->name ?? $cartItem->name;
            $orderProduct->sku = $cartItem->model->sku ?? null;
            $orderProduct->vat_rate = $cartItem->options['vat_rate'] ?? $cartItem->taxRate;
            $orderProduct->price = Product::getShoppingCartItemPrice($cartItem, $discountCode ?? null);
            $orderProduct->discount = Product::getShoppingCartItemPrice($cartItem) - $orderProduct->price;
            $productExtras = [];
            foreach ($cartItem->options as $optionId => $option) {
                if ($option['name'] ?? false) {
                    $productExtras[] = [
                        'id' => $optionId,
                        'name' => $option['name'],
                        'value' => $option['value'],
                        'price' => ProductExtraOption::find($optionId)->price,
                    ];
                }
            }
            $orderProduct->product_extras = $productExtras;

            if ($cartItem->model && $cartItem->model->isPreorderable() && $cartItem->model->stock < $cartItem->qty) {
                $orderProduct->is_pre_order = true;
                $orderProduct->pre_order_restocked_date = $cartItem->model->expected_in_stock_date;
                $orderContainsPreOrders = true;
            }

            $orderProduct->save();
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

        $orderLog = new OrderLog();
        $orderLog->order_id = $order->id;
        $orderLog->user_id = $userId;
        $orderLog->tag = 'order.created.by.admin';
        $orderLog->save();

        return [
            'success' => true,
            'order' => $order,
        ];
    }

    public function getPaymentOptions($amount): array
    {
        $options = [];

        $options[] = round($amount, 2);

        $roundedUp5 = ceil($amount / 5) * 5;
        if ($roundedUp5 != $amount) {
            $options[] = $roundedUp5;
        }

        $roundedUp10 = ceil($amount / 10) * 10;
        if ($roundedUp10 != $amount && $roundedUp10 != $roundedUp5) {
            $options[] = $roundedUp10;
        }

        $roundedUp50 = ceil($amount / 50) * 50;
        if ($roundedUp50 != $amount && $roundedUp50 != $roundedUp5 && $roundedUp50 != $roundedUp10) {
            $options[] = $roundedUp50;
        }

        $roundedUp100 = ceil($amount / 100) * 100;
        if ($roundedUp100 != $amount && $roundedUp100 != $roundedUp5 && $roundedUp100 != $roundedUp10 && $roundedUp100 != $roundedUp50) {
            $options[] = $roundedUp100;
        }

        $amounts = [];
        foreach ($options as $option) {
            $amounts[] = [
                'amount' => $option,
                'formattedAmount' => CurrencyHelper::formatPrice($option),
            ];
        }

        return array_slice($amounts, 0, 5);
    }

    public function startPinTerminalPayment(Request $request): JsonResponse
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $hasMultiplePayments = $data['hasMultiplePayments'] ?? false;
        $order = $data['order'] ?? null;
        $order = Order::find($order['id']);
        $paymentMethod = $data['paymentMethod'] ?? null;
        $paymentMethod = PaymentMethod::find($paymentMethod['id']);
        if ($hasMultiplePayments) {
            $paymentMethod = PaymentMethod::where('type', 'pos')->whereNotNull('pin_terminal_id')->first();
        }

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $pinTerminalResponse = PinTerminal::startPayment($order, $paymentMethod, $posCart);

        return response()
            ->json($pinTerminalResponse, $pinTerminalResponse['success'] ? 200 : 400);
    }

    public function markAsPaid(Request $request): JsonResponse
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $hasMultiplePayments = $data['hasMultiplePayments'] ?? false;
        $cashPaymentAmount = $data['cashPaymentAmount'] ?? false;
        $order = $data['order'] ?? null;
        $order = Order::find($order['id']);
        $paymentMethod = $data['paymentMethod'] ?? null;
        $paymentMethod = PaymentMethod::find($paymentMethod['id']);

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        if ($paymentMethod->is_cash_payment) {
            if (! $cashPaymentAmount) {
                return response()
                    ->json([
                        'success' => false,
                        'message' => 'Geen bedrag ingevoerd',
                    ], 400);
            } elseif (! $hasMultiplePayments && $cashPaymentAmount < $order->total) {
                return response()
                    ->json([
                        'success' => false,
                        'message' => 'Bedrag is te laag',
                    ], 400);
            }
        }

        $orderPayment = new OrderPayment();
        $orderPayment->amount = $cashPaymentAmount ?: $order->total;
        $orderPayment->order_id = $order->id;
        $orderPayment->payment_method_id = $paymentMethod->id;
        $orderPayment->payment_method = $paymentMethod->name;
        $orderPayment->psp = 'own';
        $orderPayment->save();
        $orderPayment->changeStatus('paid');

        if ($orderPayment->amount > $order->total) {
            $difference = $order->total - $orderPayment->amount;

            $refundOrderPayment = new OrderPayment();
            $refundOrderPayment->amount = $difference;
            $refundOrderPayment->order_id = $order->id;
            $refundOrderPayment->payment_method_id = $paymentMethod->id;
            $refundOrderPayment->payment_method = $paymentMethod->name;
            $refundOrderPayment->psp = 'own';
            $refundOrderPayment->save();
            $refundOrderPayment->changeStatus('paid');
        }

        $order->refresh();

        if ($paymentMethod->is_cash_payment && $cashPaymentAmount < $order->total && $hasMultiplePayments) {
            $paymentMethod = PaymentMethod::where('type', 'pos')->whereNotNull('pin_terminal_id')->first();
            if (! $paymentMethod) {
                return response()
                    ->json([
                        'success' => false,
                        'message' => 'Geen pin terminal gevonden, bestelling incorrect afgehandeld',
                    ], 400);
            }

            return response()
                ->json([
                    'success' => true,
                    'order' => $order,
                    'startPinTerminalPayment' => true,
                    'paymentMethod' => [
                        'id' => $paymentMethod->id,
                        'name' => $paymentMethod->name,
                        'image' => mediaHelper()->getSingleMedia($paymentMethod->image, ['widen' => 300])->url ?? '',
                    ],
                ]);
            //            $response = PinTerminal::startPayment($order, $paymentMethod, $posCart);
            //
            //            return response()
            //                ->json($response, $response['success'] ? 200 : 400);
        } else {
            POSHelper::finishPaidOrder($order, $posCart);

            $order->totalFormatted = CurrencyHelper::formatPrice($order->total);
            $order->paidAmount = $order->paidAmount;
            $order->paidAmountFormatted = CurrencyHelper::formatPrice($order->paidAmount);
            $order->changeMoney = CurrencyHelper::formatPrice($orderPayment->amount - $order->total);
            $order->shouldChangeMoney = $orderPayment->amount > $order->total;
            $orderPayments = $order->orderPayments;
            foreach ($orderPayments as $orderPayment) {
                $orderPayment->amountFormatted = CurrencyHelper::formatPrice($orderPayment->amount);
                $orderPayment->paymentMethodName = $orderPayment->paymentMethod->name;
            }

            return response()
                ->json([
                    'success' => true,
                    'order' => $order,
                    'orderPayments' => $orderPayments,
                    'startPinTerminalPayment' => false,
                    'firstPaymentMethod' => [
                        'id' => $paymentMethod->id,
                        'is_cash_payment' => $paymentMethod->is_cash_payment,
                        'name' => $paymentMethod->name,
                        'image' => mediaHelper()->getSingleMedia($paymentMethod->image, ['widen' => 300])->url ?? '',
                    ],
                ]);
        }
    }

    public function checkPinTerminalPayment(Request $request): JsonResponse
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $order = $data['order'] ?? null;
        $order = Order::find($order['id']);

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $order->refresh();

        if ($order->isPaidFor()) {
            POSHelper::finishPaidOrder($order, $posCart);

            return response()
                ->json([
                    'success' => true,
                    'pinTerminalStatus' => 'paid',
                    'pinTerminalError' => false,
                    'pinTerminalErrorMessage' => null,
                ]);
        } elseif ($order->status == 'cancelled') {
            return response()
                ->json([
                    'success' => true,
                    'pinTerminalStatus' => 'cancelled_by_customer',
                    'pinTerminalError' => true,
                    'pinTerminalErrorMessage' => 'Betaling geannuleerd door klant',
                ]);
        } else {
            return response()
                ->json([
                    'success' => true,
                    'pinTerminalStatus' => 'pending',
                    'pinTerminalError' => false,
                    'pinTerminalErrorMessage' => null,
                ]);
        }
    }

    public function closePayment(Request $request): JsonResponse
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $order = $data['order'] ?? null;
        $order = Order::find($order['id']);

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        if ($order->isPaidFor()) {
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Bestelling is al betaald',
                ]);
        } else {
            return response()
                ->json([
                    'success' => true,
                    'message' => 'De bestelling is geannuleerd',
                ]);
        }
    }

    public function getAllProducts(Request $request): JsonResponse
    {
        $products = Product::handOrderShowable()
            ->select(['id', 'name', 'images', 'price', 'ean', 'sku', 'current_price', 'discount_price'])
            ->get()
            ->map(function ($product) {
                $name = $product->getTranslation('name', app()->getLocale());
                $currentPrice = $product->currentPrice;

                return [
                    'id' => $product->id,
                    'name' => $name,
                    'image' => mediaHelper()->getSingleMedia($product->firstImage, ['widen' => 300])->url ?? '',
                    'currentPrice' => $currentPrice,
                    'currentPriceFormatted' => CurrencyHelper::formatPrice($currentPrice),
                    'search' => $name . ' ' . $product->sku . ' ' . $product->ean,
                ];
            })
            ->toArray();

        return response()
            ->json([
                'success' => true,
                'products' => $products,
            ]);
    }
}
