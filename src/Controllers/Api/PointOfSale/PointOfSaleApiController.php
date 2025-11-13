<?php

namespace Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale;

use Carbon\Carbon;
use Paynl\Payment;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Dashed\DashedCore\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Classes\POSHelper;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\PinTerminal;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
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

        $shippingMethods = ShippingMethod::all();
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->fullName = $shippingMethod->getTranslation('name', app()->getLocale());
            if (count($shippingMethod->shippingZone->zones) > 1) {
                $shippingMethod->fullName .= ' (' . implode(', ', $shippingMethod->shippingZone->zones) . ')';
            } else {
                $shippingMethod->fullName .= ' (' . $shippingMethod->shippingZone->name . ')';
            }

            $costs = $shippingMethod->costsForCart(ShoppingCart::getShippingZoneByCountry($posCart->country)->id ?? null);
            $shippingMethod->fullName .= ' ' . ($costs > 0 ? CurrencyHelper::formatPrice($costs) : 'gratis');
        }

        $chosenShippingMethod = $posCart->shipping_method_id ? ShippingMethod::find($posCart->shipping_method_id) : null;

        return response()
            ->json([
                'posIdentifier' => $posIdentifier ?? null,
                'products' => array_reverse($products ?? []),
                'shippingMethods' => $shippingMethods,
                'shippingMethodId' => $chosenShippingMethod->id ?? null,
                'shippingCosts' => $chosenShippingMethod ? CurrencyHelper::formatPrice($chosenShippingMethod->costsForCart(ShoppingCart::getShippingZoneByCountry($posCart->country)->id ?? null)) : null,
                'customerUserId' => $posCart->customer_user_id,
                'firstName' => $posCart->first_name,
                'lastName' => $posCart->last_name,
                'phoneNumber' => $posCart->phone_number,
                'email' => $posCart->email,
                'street' => $posCart->street,
                'houseNr' => $posCart->house_nr,
                'zipCode' => $posCart->zip_code,
                'city' => $posCart->city,
                'country' => $posCart->country,
                'company' => $posCart->company,
                'btwId' => $posCart->btw_id,
                'invoiceStreet' => $posCart->invoice_street,
                'invoiceHouseNr' => $posCart->invoice_house_nr,
                'invoiceZipCode' => $posCart->invoice_zip_code,
                'invoiceCity' => $posCart->invoice_city,
                'invoiceCountry' => $posCart->invoice_country,
                'note' => $posCart->note,
                'discountCode' => $posCart->discount_code,
                'customFields' => $posCart->custom_fields,
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
        //        cartHelper()->initialize($cartInstance);
        cartHelper()->setCartType($cartInstance);
        cartHelper()->emptyCart();

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $discountCode = $discountCode ?? $posCart->discount_code;

        $products = $posCart->products ?? [];

        foreach ($products ?: [] as $chosenProduct) {
            $product = Product::find($chosenProduct['id']);
            if (($chosenProduct['quantity'] ?? 0) > 0) {
                $productPrice = (($chosenProduct['customProduct'] ?? false) || ($chosenProduct['isCustomPrice'] ?? false)) ? $chosenProduct['singlePrice'] : $product->getOriginal('price');
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
                $options['vat_rate'] = $chosenProduct['vat_rate'] ?? '';
                $options['singlePrice'] = $chosenProduct['singlePrice'];
                if ($chosenProduct['customProduct'] ?? false) {
                    $options['customProduct'] = true;
                }

                if ($chosenProduct['isCustomPrice'] ?? false) {
                    $options['isCustomPrice'] = true;
                }

                if ($product->id ?? false) {
                    \Cart::instance($cartInstance)->add($product->id, $product->name ?? $chosenProduct['name'], $chosenProduct['quantity'], $productPrice, $options)
                        ->associate(Product::class);
                } else {
                    \Cart::instance($cartInstance)
                        ->add($chosenProduct['customId'], $product->name ?? $chosenProduct['name'], $chosenProduct['quantity'], $productPrice, $options);
                }
            }
        }

        cartHelper()->initialize($cartInstance);
        cartHelper()->applyDiscountCode($discountCode);
        $activeDiscountCode = cartHelper()->getDiscountCodeString();

        $posCart->discount_code = $activeDiscountCode ?? null;
        $posCart->save();

        //        cartHelper()->initialize();

        if ($posCart->shipping_method_id) {
            cartHelper()->setShippingMethod($posCart->shipping_method_id);
            cartHelper()->setShippingZone(ShoppingCart::getShippingZoneByCountry($posCart->country ?: Countries::getAllSelectedCountries()[0])->id ?? null);
        }

        cartHelper()->updateData();

        $discount = CurrencyHelper::formatPrice(cartHelper()->getDiscount());
        $vat = CurrencyHelper::formatPrice(cartHelper()->getTax());
        $vatPercentages = cartHelper()->getTaxPercentages();
        foreach ($vatPercentages as $key => $value) {
            $vatPercentages[$key] = CurrencyHelper::formatPrice($value);
        }
        $subTotal = CurrencyHelper::formatPrice(cartHelper()->getSubtotal());
        $total = CurrencyHelper::formatPrice(cartHelper()->getTotal());
        $totalUnformatted = cartHelper()->getTotal();

        $paymentMethods = ShoppingCart::getPaymentMethods('pos');

        foreach ($paymentMethods as &$paymentMethod) {
            $paymentMethod['fullName'] = $paymentMethod->getTranslation('name', app()->getLocale());
            $paymentMethod['image'] = $paymentMethod['image'] ? (mediaHelper()->getSingleMedia($paymentMethod['image'], ['widen' => 300])->url ?? '') : '';
        }

        $shippingMethods = ShippingMethod::all();
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->fullName = $shippingMethod->getTranslation('name', app()->getLocale());
            if (count($shippingMethod->shippingZone->zones) > 1) {
                $shippingMethod->fullName .= ' (' . implode(', ', $shippingMethod->shippingZone->zones) . ')';
            } else {
                $shippingMethod->fullName .= ' (' . $shippingMethod->shippingZone->name . ')';
            }

            $costs = $shippingMethod->costsForCart(ShoppingCart::getShippingZoneByCountry($posCart->country)->id ?? null);
            $shippingMethod->fullName .= ' ' . ($costs > 0 ? CurrencyHelper::formatPrice($costs) : 'gratis');
        }

        $chosenShippingMethod = $posCart->shipping_method_id ? ShippingMethod::find($posCart->shipping_method_id) : null;
        $shippingCosts = $chosenShippingMethod ? $chosenShippingMethod->costsForCart(ShoppingCart::getShippingZoneByCountry($posCart->country)->id ?? null) : null;

        return [
            'products' => array_reverse($products ?? []),
            'discountCode' => $discountCode ?? null,
            'activeDiscountCode' => $activeDiscountCode ?? null,
            'discount' => $discount ?? null,
            'vat' => $vat ?? null,
            'vatPercentages' => $vatPercentages ?? null,
            'subTotal' => $subTotal ?? null,
            'total' => $total ?? null,
            'totalUnformatted' => $totalUnformatted ?? null,
            'paymentMethods' => $paymentMethods,
            'shippingMethods' => $shippingMethods ?? null,
            'shippingMethodId' => $chosenShippingMethod->id ?? null,
            'shippingCosts' => CurrencyHelper::formatPrice($shippingCosts),
            'shippingCostsUnformatted' => $shippingCosts,
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
                'success' => true,
            ]);
    }

    public function sendInvoice(Request $request)
    {
        $data = $request->all();

        $orderId = $data['orderId'] ?? null;

        $order = Order::find($orderId);

        if (! $order) {
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Bestelling niet gevonden',
                ], 404);
        } elseif (! $order->email) {
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Geen e-mailadres gekoppeld aan de bestelling',
                ], 404);
        }

        Orders::sendNotification($order, $order->email, auth()->user());

        return response()
            ->json([
                'success' => true,
            ]);
    }

    public function searchProducts(Request $request)
    {
        $data = $request->all();

        $search = str($data['search'] ?? null)->trim()->toString();

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
                    'products' => array_reverse($products ?? []),
                    'success' => true,
                ]);
        } else {
            return response()
                ->json([
                    'products' => array_reverse($products ?? []),
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
                $product['price'] = $selectedProduct->currentPrice * $product['quantity'];
                $product['priceFormatted'] = CurrencyHelper::formatPrice($product['price']);
            }
        }

        if (! $productAlreadyInCart) {
            $products[] = [
                'id' => $selectedProduct['id'],
                'identifier' => Str::random(),
                'name' => $selectedProduct->getTranslation('name', app()->getLocale()),
                'image' => mediaHelper()->getSingleMedia($selectedProduct->firstImage, ['widen' => 300])->url ?? '',
                'quantity' => 1,
                'singlePrice' => $selectedProduct->currentPrice,
                'price' => $selectedProduct->currentPrice,
                'priceFormatted' => CurrencyHelper::formatPrice($selectedProduct->currentPrice),
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

            $discountCode = DiscountCode::usable()->where('code', $productSearchQuery)->first();

            if ($discountCode) {
                $posCart->discount_code = $discountCode->code;
                $posCart->save();

                return response()
                    ->json([
                        'products' => array_reverse($products ?? []),
                        'message' => 'Korting toegepast',
                        'discountCode' => $discountCode->code,
                        'success' => true,
                    ]);
            }

            return response()
                ->json([
                    'products' => array_reverse($products ?? []),
                    'message' => 'Product niet gevonden',
                    'success' => false,
                ], 404);
        }

        $products = $this->addProductToCart($posCart, $selectedProduct);

        return response()
            ->json([
                'products' => array_reverse($products ?? []),
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
            })
                ->values()
                ->toArray();
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
                    $product['priceFormatted'] = CurrencyHelper::formatPrice($product['price']);
                }
            }
        }

        $posCart->products = $products;
        $posCart->save();

        return response()
            ->json([
                'products' => array_reverse($products ?? []),
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

            $postPay = $paymentMethod->postpay;
            if ($postPay) {
                POSHelper::finishPaidOrder($order, $posCart, 'waiting_for_confirmation', 'unhandled');
            }

            return response()
                ->json([
                    'success' => true,
                    'order' => $order,
                    'suggestedCashPaymentAmounts' => $suggestedCashPaymentAmounts,
                    'paymentMethod' => [
                        'id' => $paymentMethod->id,
                        'name' => $paymentMethod->getTranslation('name', app()->getLocale()),
                        'image' => $paymentMethod->image ? (mediaHelper()->getSingleMedia($paymentMethod->image, ['widen' => 300])->url ?? '') : '',
                        'isCashPayment' => $paymentMethod->is_cash_payment,
                    ],
                    'isPinTerminalPayment' => $isPinTerminalPayment,
                    'postPay' => $postPay,
                    'orderUrl' => route('filament.dashed.resources.orders.view', ['record' => $order->id]),
                ]);
        } else {
            return response()
                ->json([
                    'success' => false,
                    'message' => $response['message'],
                ], 500);
        }
    }

    public function selectShippingMethod(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $cartInstance = $data['cartInstance'] ?? null;
        $orderOrigin = $data['orderOrigin'] ?? null;
        $userId = $data['userId'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $shippingMethod = ShippingMethod::find($data['shippingMethodId']);

        if (! $shippingMethod) {
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Verzendmethode niet gevonden',
                ], 500);
        }

        $posCart->shipping_method_id = $shippingMethod->id;
        $posCart->save();

        return response()
            ->json([
                'success' => true,
                'shippingMethodId' => $shippingMethod->id,
                'shippingCosts' => CurrencyHelper::formatPrice($shippingMethod->costsForCart(ShoppingCart::getShippingZoneByCountry($posCart->country)->id ?? null)),
            ]);
    }

    public function removeShippingMethod(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $cartInstance = $data['cartInstance'] ?? null;
        $orderOrigin = $data['orderOrigin'] ?? null;
        $userId = $data['userId'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();
        $posCart->shipping_method_id = null;
        $posCart->save();

        return response()
            ->json([
                'success' => true,
            ]);
    }

    public function updateCustomerData(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $cartInstance = $data['cartInstance'] ?? null;
        $orderOrigin = $data['orderOrigin'] ?? null;
        $userId = $data['userId'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $posCart->save();

        return response()
            ->json([
                'success' => true,
            ]);
    }

    public function createOrder($cartInstance, $posCart, $paymentMethodId, $orderOrigin, $userId): array
    {
        $this->updateCart($cartInstance, $posCart->identifier);
        $cartItems = cartHelper()->getCartItems();

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

        $shippingMethod = ShippingMethod::find($posCart->shipping_method_id);

        if ($posCart->discount_code) {
            $discountCode = DiscountCode::usable()->where('code', $posCart->discount_code)->first();

            if (! $discountCode) {
                session(['discountCode' => '']);
                $discountCode = '';
            } elseif ($discountCode && ! $discountCode->isValidForCart($posCart->email, $cartInstance)) {
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
        $order->first_name = $posCart->first_name;
        $order->last_name = $posCart->last_name;
        $order->email = $posCart->email;
        $order->user_id = $posCart->customer_user_id;
        //        $order->gender = $posCart->gender;
        //        $order->date_of_birth = $posCart->date_of_birth ? Carbon::parse($this->date_of_birth) : null;
        $order->phone_number = $posCart->phone_number;
        $order->street = $posCart->street;
        $order->house_nr = $posCart->house_nr;
        $order->zip_code = $posCart->zip_code;
        $order->city = $posCart->city;
        $order->country = $posCart->country;
        $order->company_name = $posCart->company;
        $order->btw_id = $posCart->btw_id;
        $order->note = $posCart->note;
        $order->invoice_street = $posCart->invoice_street;
        $order->invoice_house_nr = $posCart->invoice_house_nr;
        $order->invoice_zip_code = $posCart->invoice_zip_code;
        $order->invoice_city = $posCart->invoice_city;
        $order->invoice_country = $posCart->invoice_country;
        $order->invoice_id = 'PROFORMA';

        session(['discountCode' => $posCart->discount_code]);
        $subTotal = cartHelper()->getSubtotal();
        $discount = cartHelper()->getDiscount();
        $btw = cartHelper()->getTax();
        $btwPercentages = cartHelper()->getTaxPercentages();
        $total = cartHelper()->getTotal();
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
                $orderProduct->btw = cartHelper()->getVatForPaymentMethod();
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
            $orderProduct->btw = cartHelper()->getVatForShippingMethod();
            $orderProduct->vat_rate = cartHelper()->getVatRateForShippingMethod();
            $orderProduct->discount = cartHelper()->getVatForShippingMethod() - $orderProduct->btw;
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
                        'image' => $paymentMethod->image ? (mediaHelper()->getSingleMedia($paymentMethod->image, ['widen' => 300])->url ?? '') : '',
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
            if (! $order->pos_order_handled) {
                POSHelper::finishPaidOrder($order, $posCart);
            }

            $orderPayment = $order->orderPayments()->where('status', 'paid')->first();
            $paymentMethod = $orderPayment->paymentMethod;
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
                    'pinTerminalStatus' => 'paid',
                    'pinTerminalError' => false,
                    'pinTerminalErrorMessage' => null,
                    'order' => $order,
                    'orderPayments' => $orderPayments,
                    'startPinTerminalPayment' => false,
                    'firstPaymentMethod' => [
                        'id' => $paymentMethod->id,
                        'is_cash_payment' => $paymentMethod->is_cash_payment,
                        'name' => $paymentMethod->name,
                        'image' => $paymentMethod->image ? (mediaHelper()->getSingleMedia($paymentMethod->image, ['widen' => 300])->url ?? '') : '',
                    ],
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

            $order->changeStatus('cancelled');

            return response()
                ->json([
                    'success' => true,
                    'message' => 'De bestelling is geannuleerd',
                ]);
        }
    }

    public function getAllProducts(Request $request): JsonResponse
    {
        $clearCache = $request->get('clearCache', false);
        if ($clearCache) {
            Cache::forget('pos_products');
        }

        //        $products = Cache::remember('pos_products', 60 * 60 * 24 * 7, function () { //Cache one week
        $products = Product::handOrderShowable()
            ->select(['id', 'name', 'images', 'price', 'ean', 'sku', 'current_price', 'discount_price', 'use_stock', 'stock', 'stock_status'])
            ->get()
            ->map(function ($product) {
                $name = $product->getTranslation('name', app()->getLocale());
                $currentPrice = $product->currentPrice;
                //                    $image = mediaHelper()->getSingleMedia($product->firstImage, ['widen' => 300])->url ?? '';

                return [
                    'id' => $product->id,
                    'name' => $name,
                    'stock' => $product->directSellableStock(),
                    'actual_stock' => $product->stock,
//                        'image' => $image ? "data:image/png;base64,".base64_encode(file_get_contents($image)) : '',
                    'currentPrice' => $currentPrice,
                    'currentPriceFormatted' => CurrencyHelper::formatPrice($currentPrice),
                    'search' => $name . ' ' . $product->sku . ' ' . $product->ean,
                ];
            })
            ->toArray();

        //            return $products;
        //        });


        return response()
            ->json([
                'success' => true,
                'products' => $products,
            ]);
    }

    public function updateProductInfo(Request $request): JsonResponse
    {
        $data = $request->all();

        $products = $data['products'] ?? null;

        foreach ($products as &$product) {
            $thisProduct = Product::find($product['id']);
            $product['stock'] = $thisProduct->directSellableStock();
            $product['actual_stock'] = $thisProduct->stock;
            $product['image'] = $thisProduct->firstImage ? (mediaHelper()->getSingleMedia($thisProduct->firstImage, ['widen' => 300])->url ?? '') : '';
        }

        return response()
            ->json([
                'success' => true,
                'products' => $products,
            ]);
    }

    public function updateProduct(Request $request): JsonResponse
    {
        $data = $request->all();

        $product = $data['product'] ?? null;

        $thisProduct = Product::find($product['id']);
        $thisProduct->stock = $product['actual_stock'];
        $thisProduct->save();

        return response()
            ->json([
                'success' => true,
            ]);
    }

    public function updateSearchQueryInputmode(Request $request): JsonResponse
    {
        $data = $request->all();

        $searchQueryInputmode = $data['searchQueryInputmode'] ?? null;
        $userId = $data['userId'] ?? null;

        Customsetting::set('pos_search_query_inputmode', $searchQueryInputmode);

        return response()
            ->json([
                'success' => true,
            ]);
    }

    public function cancelOrder(Request $request): JsonResponse
    {
        $data = $request->all();

        $order = $data['order'] ?? null;
        $data = $order['cancelData'] ?? null;
        $userId = $data['userId'] ?? null;

        $order = Order::find($order['id']);

        if (in_array($order->order_origin, ['own', 'pos']) && $order->invoice_id != 'PROFORMA') {
            $sendCustomerEmail = $data['sendCustomerEmail'];
            $productsMustBeReturned = $data['productsMustBeReturned'];
            $restock = $data['restock'];
            $refundDiscountCosts = $data['refundDiscountCosts'];

            $cancelledProductsQuantity = 0;
            $orderProducts = $data['orderProducts'];
            foreach ($orderProducts as $orderProduct) {
                $cancelledProductsQuantity += $orderProduct['refundQuantity'];
                $orderProduct['refundQuantity'] = $orderProduct['refundQuantity'];
            }

            $extraOrderLine = $data['extraOrderLine'];
            $extraOrderLineName = $data['extraOrderLineName'] ?? '';
            $extraOrderLinePrice = $data['extraOrderLinePrice'] ?? '';

            if (! $extraOrderLine && $cancelledProductsQuantity == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geen producten geretourneerd',
                ]);
            }

            $newOrder = $order->markAsCancelledWithCredit($sendCustomerEmail, $productsMustBeReturned, $restock, $refundDiscountCosts, $extraOrderLineName, $extraOrderLinePrice, collect($orderProducts), $data['fulfillmentStatus'], $data['paymentMethodId']);

            if (Customsetting::get('pos_auto_print_receipt', null, true)) {
                try {
                    $newOrder->printReceipt();
                } catch (\Exception $e) {
                }
            }

            return response()->json([
                'success' => true,
            ]);
        } else {
            return response()->json([
                'success' => true,
            ]);
        }

        return response()
            ->json([
                'success' => true,
            ]);
    }

    public function retrieveOrders(Request $request): JsonResponse
    {
        $data = $request->all();

        $userId = $data['userId'] ?? null;
        $searchOrderQuery = $data['searchOrderQuery'] ?? null;
        $fulfillmentStatusOptions = Orders::getFulfillmentStatusses();
        $paymentMethods = PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->pluck('name', 'id')->toArray();
        $paymentMethodId = PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->where('is_cash_payment', 1)->first()->id ?? null;

        if ($searchOrderQuery && str($searchOrderQuery)->startsWith('order-')) {
            $orderId = str($searchOrderQuery)->replace('order-', '');
            $order = Order::find($orderId);
            if ($order) {
                $vatPercentages = $order->vat_percentages;
                foreach ($vatPercentages as $percentage => &$value) {
                    $value = CurrencyHelper::formatPrice($value);
                }

                $orderProducts = $order->orderProducts->map(function ($orderProduct) {
                    return [
                        'id' => $orderProduct->id,
                        'name' => $orderProduct->name,
                        'sku' => $orderProduct->sku,
                        'product_id' => $orderProduct->product_id,
                        'quantity' => $orderProduct->quantity,
                        'discount' => $orderProduct->discount,
                        'product_extras' => $orderProduct->product_extras,
                        'vat_rate' => $orderProduct->vat_rate,
                        'refundQuantity' => 0,
                        'price' => $orderProduct->price,
                        'priceFormatted' => CurrencyHelper::formatPrice($orderProduct->price),
                        'image' => $orderProduct->product ? (mediaHelper()->getSingleMedia($orderProduct->product->firstImage, ['widen' => 300])->url ?? '') : '',
                    ];
                });

                return response()->json([
                    'success' => true,
                    'order' => [
                        'id' => $order->id,
                        'invoiceId' => $order->invoice_id,
                        'createdAt' => $order->created_at->format('d-m-Y H:i'),
                        'total' => $order->total,
                        'totalFormatted' => CurrencyHelper::formatPrice($order->total),
                        'taxFormatted' => CurrencyHelper::formatPrice($order->btw),
                        'discountFormatted' => $order->discount > 0 ? CurrencyHelper::formatPrice($order->discount) : '',
                        'vatPercentages' => $vatPercentages,
                        'totalProducts' => $order->orderProducts()->sum('quantity'),
                        'status' => $order->status,
                        'orderOrigin' => $order->order_origin,
                        'fulfillmenStatus' => Orders::getFulfillmentStatusses()[$order->fulfillment_status] ?? Orders::getReturnStatusses()[$order->fulfillment_status],
                        'time' => $order->created_at->format('H:i'),
                        'shippingMethod' => $order->shippingMethod ? $order->shippingMethod->name : 'niet gekozen',
                        'email' => $order->email,
                        'cancelData' => [
                            'extraOrderLine' => false,
                            'extraOrderLineName' => '',
                            'extraOrderLinePrice' => '',
                            'fulfillmentStatus' => $order->fulfillment_status,
                            'fulfillmentStatusOptions' => $fulfillmentStatusOptions,
                            'paymentMethods' => $paymentMethods,
                            'paymentMethodId' => $paymentMethodId,
                            'sendCustomerEmail' => false,
                            'productsMustBeReturned' => false,
                            'restock' => false,
                            'refundDiscountCosts' => false,
                            'orderProducts' => $orderProducts,
                        ],
                        'orderProducts' => $orderProducts,
                        'orderPayments' => $order->orderPayments->map(function ($orderPayment) {
                            return [
                                'amount' => $orderPayment->amount,
                                'amountFormatted' => CurrencyHelper::formatPrice($orderPayment->amount),
                                'paymentMethod' => $orderPayment->paymentMethod ? $orderPayment->paymentMethod->name : $orderPayment->payment_method,
                                'status' => $orderPayment->status,
                                'createdAt' => $orderPayment->created_at->format('d-m-Y H:i'),
                            ];
                        }),
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Bestelling niet gevonden',
                ]);
            }
        }

        $firstOrder = null;
        $now = now()->startOfDay();

        $endDate = now()->subQuarter()->startOfDay();

        $orders = Order::orderBy('created_at', 'desc');

        if (! $searchOrderQuery) {
            $orders->where('created_at', '>=', $endDate);
        } else {
            $orders->quickSearch($searchOrderQuery);
        }

        $orders = $orders
            ->get()
            ->groupBy(function ($order) {
                return $order->created_at->format('Y-m-d'); // Group orders by the date part
            })
            ->map(function ($orders, $date) {
                $fulfillmentStatusOptions = Orders::getFulfillmentStatusses();
                $paymentMethods = PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->pluck('name', 'id')->toArray();
                $paymentMethodId = PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->where('is_cash_payment', 1)->first()->id ?? null;

                return [
                    'date' => Carbon::parse($date)->isToday() ? 'Vandaag' : (Carbon::parse($date)->isYesterday() ? 'Gisteren' : Carbon::parse($date)->format('d M')),
                    'orders' => $orders->map(function ($order) use ($fulfillmentStatusOptions, $paymentMethods, $paymentMethodId) {
                        $vatPercentages = $order->vat_percentages;
                        foreach ($vatPercentages as $percentage => &$value) {
                            $value = CurrencyHelper::formatPrice($value);
                        }

                        $orderProducts = $order->orderProducts->map(function ($orderProduct) {
                            return [
                                'id' => $orderProduct->id,
                                'name' => $orderProduct->name,
                                'sku' => $orderProduct->sku,
                                'quantity' => $orderProduct->quantity,
                                'product_id' => $orderProduct->product_id,
                                'discount' => $orderProduct->discount,
                                'product_extras' => $orderProduct->product_extras,
                                'vat_rate' => $orderProduct->vat_rate,
                                'refundQuantity' => 0,
                                'price' => $orderProduct->price,
                                'priceFormatted' => CurrencyHelper::formatPrice($orderProduct->price),
                                'image' => $orderProduct->product ? (mediaHelper()->getSingleMedia($orderProduct->product->firstImage, ['widen' => 300])->url ?? '') : '',
                            ];
                        });

                        return [
                            'id' => $order->id,
                            'invoiceId' => $order->invoice_id,
                            'createdAt' => $order->created_at->format('d-m-Y H:i'),
                            'total' => $order->total,
                            'totalFormatted' => CurrencyHelper::formatPrice($order->total),
                            'taxFormatted' => CurrencyHelper::formatPrice($order->btw),
                            'discountFormatted' => $order->discount > 0 ? CurrencyHelper::formatPrice($order->discount) : '',
                            'vatPercentages' => $vatPercentages,
                            'totalProducts' => $order->orderProducts()->sum('quantity'),
                            'status' => $order->status,
                            'fulfillmenStatus' => Orders::getFulfillmentStatusses()[$order->fulfillment_status] ?? Orders::getReturnStatusses()[$order->fulfillment_status],
                            'orderOrigin' => $order->order_origin,
                            'time' => $order->created_at->format('H:i'),
                            'shippingMethod' => $order->shippingMethod ? $order->shippingMethod->name : 'niet gekozen',
                            'email' => $order->email,
                            'cancelData' => [
                                'extraOrderLine' => false,
                                'extraOrderLineName' => '',
                                'extraOrderLinePrice' => '',
                                'fulfillmentStatus' => $order->fulfillment_status,
                                'fulfillmentStatusOptions' => $fulfillmentStatusOptions,
                                'paymentMethods' => $paymentMethods,
                                'paymentMethodId' => $paymentMethodId,
                                'sendCustomerEmail' => false,
                                'productsMustBeReturned' => false,
                                'restock' => false,
                                'refundDiscountCosts' => false,
                                'orderProducts' => $orderProducts,
                            ],
                            'orderProducts' => $orderProducts,
                            'orderPayments' => $order->orderPayments->map(function ($orderPayment) {
                                return [
                                    'amount' => $orderPayment->amount,
                                    'amountFormatted' => CurrencyHelper::formatPrice($orderPayment->amount),
                                    'paymentMethod' => $orderPayment->paymentMethod ? $orderPayment->paymentMethod->name : $orderPayment->payment_method,
                                    'status' => $orderPayment->status,
                                    'createdAt' => $orderPayment->created_at->format('d-m-Y H:i'),
                                ];
                            }),
                        ];
                    }),
                ];
            })
            ->values() // Reset the keys for easier handling in JSON responses
            ->toArray();

        foreach ($orders as $date) {
            foreach ($date['orders'] as $order) {
                if (! $firstOrder) {
                    $firstOrder = $order;
                }
            }
        }

        return response()
            ->json([
                'success' => true,
                'orders' => $orders,
                'firstOrder' => $firstOrder,
            ]);
    }

    public function retrieveCartForCustomer(Request $request): JsonResponse
    {
        $data = $request->all();

        $userId = $data['userId'] ?? null;

        $posCart = POSCart::where('user_id', $userId)->where('status', 'active')->first();
        if ($posCart) {
            $cartInstance = 'customer-pos';
            $posIdentifier = $posCart['identifier'];
            $discountCode = $posCart['discount_code'];

            return response()
                ->json($this->updateCart(
                    $cartInstance,
                    $posIdentifier,
                    $discountCode
                ));
        }else{
            return response()
                ->json([
                    'success' => false,
                    'message' => 'Geen actieve winkelwagen gevonden voor deze klant',
                ], 404);
        }
    }
}
