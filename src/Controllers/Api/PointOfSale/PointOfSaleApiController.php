<?php

namespace Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Classes\POSHelper;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Classes\VatDisplay;
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
        $products = [];

        if ($posCart) {
            $posIdentifier = $posCart->identifier;
            $products = $posCart->products ?? [];
        } else {
            $posIdentifier = uniqid();
            $posCart = new POSCart();
            $posCart->identifier = $posIdentifier;
            $posCart->user_id = $userId;
            $posCart->status = 'active';
            $posCart->save();
        }

        $isExVatInit = (bool) ($posCart->prices_ex_vat ?? false);
        $modeInit = $isExVatInit ? 'ex' : 'incl';

        foreach ($products ?? [] as $productKey => &$product) {
            if (! isset($product['customProduct']) || $product['customProduct'] == false) {
                $dbProduct = Product::find($product['id'] ?? 0);
                if (! $dbProduct) {
                    unset($products[$productKey]);

                    continue;
                }

                $product['image'] = $dbProduct->firstImage;
                $product['name'] = $dbProduct->getTranslation('name', app()->getLocale());
                $product['singlePrice'] = (float) ($product['singlePrice'] ?? $dbProduct->currentPrice);
                $product['price'] = (float) ($product['price'] ?? ($product['singlePrice'] * (int) ($product['quantity'] ?? 1)));
                $product['priceFormatted'] = CurrencyHelper::formatPrice($product['price']);
                $product['vat_rate'] = $product['vat_rate'] ?? (float) ($dbProduct->vat_rate ?? $dbProduct->tax_rate ?? 21);
            }

            $lineDisplayInit = VatDisplay::formatLinePrice(
                (float) ($product['price'] ?? 0),
                $product['vat_rate'] ?? 21,
                $modeInit,
            );
            $product['priceFormattedPrimary'] = $lineDisplayInit['primary'];
            $product['priceFormattedSecondary'] = $lineDisplayInit['secondary'];
        }
        unset($product);

        $shippingMethods = ShippingMethod::all();
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->fullName = $shippingMethod->getTranslation('name', app()->getLocale());

            if ($shippingMethod->shippingZone && count($shippingMethod->shippingZone->zones) > 1) {
                $shippingMethod->fullName .= ' ('.implode(', ', $shippingMethod->shippingZone->zones).')';
            } elseif ($shippingMethod->shippingZone) {
                $shippingMethod->fullName .= ' ('.$shippingMethod->shippingZone->name.')';
            }

            $shippingZone = $posCart->country ? ShoppingCart::getShippingZoneByCountry($posCart->country) : null;
            $costs = $shippingMethod->costsForCart($shippingZone->id ?? null);
            $shippingMethod->fullName .= ' '.($costs > 0 ? CurrencyHelper::formatPrice($costs) : 'gratis');
        }

        $chosenShippingMethod = $posCart->shipping_method_id ? ShippingMethod::find($posCart->shipping_method_id) : null;

        return response()->json([
            'posIdentifier' => $posIdentifier ?? null,
            'isExVat' => $isExVatInit,
            'products' => array_reverse(array_values($products ?? [])),
            'shippingMethods' => $shippingMethods,
            'shippingMethodId' => $chosenShippingMethod->id ?? null,
            'shippingCosts' => $chosenShippingMethod
                ? CurrencyHelper::formatPrice($chosenShippingMethod->costsForCart(
                    optional(ShoppingCart::getShippingZoneByCountry($posCart->country))->id
                ))
                : null,

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

        return response()->json(
            $this->updateCart($cartInstance, $posIdentifier, $discountCode)
        );
    }

    public function updateCart(string $cartInstance, string $posIdentifier, ?string $discountCode = null): array
    {
        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        if (! $posCart) {
            return [
                'success' => false,
                'message' => 'POS cart niet gevonden',
            ];
        }

        $discountCodeString = $discountCode !== null ? (string) $discountCode : (string) ($posCart->discount_code ?? '');

        $discountCodeModel = null;
        if ($discountCodeString !== '') {
            $discountCodeModel = DiscountCode::usable()->where('code', $discountCodeString)->first();
            if (! $discountCodeModel) {
                $discountCodeString = '';
            }
        }

        $posCart->discount_code = $discountCodeString ?: null;
        $posCart->save();

        $totals = $this->calculatePosCartTotals($posCart, $discountCodeModel);

        $chosenShippingMethod = $posCart->shipping_method_id ? ShippingMethod::find($posCart->shipping_method_id) : null;
        $shippingZone = $posCart->country ? ShoppingCart::getShippingZoneByCountry($posCart->country) : null;
        $shippingCosts = $chosenShippingMethod
            ? (float) $chosenShippingMethod->costsForCart($shippingZone->id ?? null)
            : 0.0;

        $totalUnformatted = (float) $totals['subtotal'] + (float) $shippingCosts;

        $isExVat = (bool) ($posCart->prices_ex_vat ?? false);
        $mode = $isExVat ? 'ex' : 'incl';

        $vatPercentages = $totals['vatPercentages'] ?? [];
        foreach ($vatPercentages as $key => $value) {
            $vatPercentages[$key] = CurrencyHelper::formatPrice((float) $value);
        }

        $subtotalIncl = (float) ($totals['subtotal'] ?? 0);
        $vatTotal = (float) ($totals['vat'] ?? 0);
        $subtotalEx = $subtotalIncl - $vatTotal;

        $products = $posCart->products ?? [];
        foreach ($products as &$product) {
            $lineDisplay = VatDisplay::formatLinePrice(
                (float) ($product['price'] ?? 0),
                $product['vat_rate'] ?? 21,
                $mode,
            );
            $product['priceFormattedPrimary'] = $lineDisplay['primary'];
            $product['priceFormattedSecondary'] = $lineDisplay['secondary'];
        }
        unset($product);

        $paymentMethods = ShoppingCart::getPaymentMethods('pos');
        foreach ($paymentMethods as &$paymentMethod) {
            $paymentMethod['fullName'] = $paymentMethod->getTranslation('name', app()->getLocale());
            $paymentMethod['image'] = $paymentMethod['image']
                ? (mediaHelper()->getSingleMedia($paymentMethod['image'], ['widen' => 300])->url ?? '')
                : '';
        }

        $shippingMethods = ShippingMethod::all();
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->fullName = $shippingMethod->getTranslation('name', app()->getLocale());

            if ($shippingMethod->shippingZone && count($shippingMethod->shippingZone->zones) > 1) {
                $shippingMethod->fullName .= ' ('.implode(', ', $shippingMethod->shippingZone->zones).')';
            } elseif ($shippingMethod->shippingZone) {
                $shippingMethod->fullName .= ' ('.$shippingMethod->shippingZone->name.')';
            }

            $costs = $shippingMethod->costsForCart($shippingZone->id ?? null);
            $shippingMethod->fullName .= ' '.($costs > 0 ? CurrencyHelper::formatPrice($costs) : 'gratis');
        }

        return [
            'products' => array_reverse(array_values($products)),
            'discountCode' => $discountCodeString ?: null,
            'activeDiscountCode' => $discountCodeString ?: null,

            'discount' => CurrencyHelper::formatPrice((float) ($totals['discount'] ?? 0)),
            'vat' => CurrencyHelper::formatPrice((float) ($totals['vat'] ?? 0)),
            'vatPercentages' => $vatPercentages,

            'isExVat' => $isExVat,
            'subTotal' => $isExVat
                ? CurrencyHelper::formatPrice($subtotalEx)
                : CurrencyHelper::formatPrice($subtotalIncl),
            'subTotalIncl' => CurrencyHelper::formatPrice($subtotalIncl),
            'subTotalEx' => CurrencyHelper::formatPrice($subtotalEx),
            'total' => CurrencyHelper::formatPrice($totalUnformatted),
            'totalUnformatted' => $totalUnformatted,

            'paymentMethods' => $paymentMethods,
            'shippingMethods' => $shippingMethods,
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
            return response()->json([
                'success' => false,
                'message' => 'Bestelling niet gevonden',
            ], 404);
        }

        $order->printReceipt($isCopy);

        return response()->json(['success' => true]);
    }

    public function sendInvoice(Request $request)
    {
        $data = $request->all();
        $orderId = $data['orderId'] ?? null;

        $order = Order::find($orderId);

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Bestelling niet gevonden',
            ], 404);
        } elseif (! $order->email) {
            return response()->json([
                'success' => false,
                'message' => 'Geen e-mailadres gekoppeld aan de bestelling',
            ], 404);
        }

        Orders::sendNotification($order, $order->email, auth()->user());

        return response()->json(['success' => true]);
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

        return response()->json([
            'products' => $products ?? [],
            'success' => true,
        ]);
    }

    public function insertOrderInPOSCart(Request $request)
    {
        $data = $request->all();

        $order = $data['order'] ?? null;
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $posCart->products = [
            [
                'id' => null,
                'name' => 'Bestelling '.$order['invoiceId'],
                'price' => $order['total'],
                'quantity' => 1,
                'singlePrice' => $order['total'],
                'priceFormatted' => CurrencyHelper::formatPrice($order['total']),
                'customProduct' => true,
                'vatPercentage' => 0,
                'product' => null,
                'identifier' => Str::random(),
                'customId' => 'custom-'.rand(1, 10000000),
                'extra' => [],
            ],
        ];
        $posCart->save();

        return response()->json(['success' => true]);
    }

    public function addCustomProduct(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $name = trim($data['name'] ?? '');
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $price = (float) ($data['price'] ?? 0);
        $vatRate = (float) ($data['vat_rate'] ?? 21);

        if (! $name) {
            return response()->json(['success' => false, 'message' => 'Productnaam is verplicht'], 422);
        }

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        if (! $posCart) {
            return response()->json(['success' => false, 'message' => 'Winkelwagen niet gevonden'], 404);
        }

        // Cart rows always hold incl-VAT. If the cashier entered ex-VAT, convert up.
        $singlePriceIncl = $posCart->prices_ex_vat
            ? $price * (1 + max(0.0, $vatRate) / 100)
            : $price;

        $product = [
            'id' => null,
            'product' => null,
            'name' => $name,
            'quantity' => $quantity,
            'singlePrice' => $singlePriceIncl,
            'price' => $singlePriceIncl * $quantity,
            'priceFormatted' => CurrencyHelper::formatPrice($singlePriceIncl * $quantity),
            'vat_rate' => $vatRate,
            'customProduct' => true,
            'isCustomPrice' => true,
            'extra' => [],
            'identifier' => Str::random(),
            'customId' => 'custom-'.rand(1, 10000000),
        ];

        $products = $posCart->products ?? [];
        $products[] = $product;
        $posCart->products = array_values($products);
        $posCart->save();

        return response()->json([
            'success' => true,
            'product' => $product,
            'products' => array_reverse($posCart->products),
        ]);
    }

    public function addProduct(Request $request)
    {
        $data = $request->all();

        $productId = $data['productId'] ?? null;
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $selectedProduct = Product::handOrderShowable()->find($productId);

        if ($selectedProduct) {
            $products = $this->addProductToCart($posCart, $selectedProduct);

            return response()->json([
                'products' => array_reverse($products ?? []),
                'success' => true,
            ]);
        }

        return response()->json([
            'products' => array_reverse($posCart?->products ?? []),
            'message' => 'Product niet gevonden',
            'success' => false,
        ], 404);
    }

    public function addProductToCart(POSCart $POSCart, Product $selectedProduct): array
    {
        $productAlreadyInCart = false;
        $customerUser = $POSCart->customer_user_id ? User::find($POSCart->customer_user_id) : null;
        $unitPrice = (float) $selectedProduct->priceForUser($customerUser);

        $products = $POSCart->products ?? [];
        foreach ($products as &$product) {
            if (($product['id'] ?? null) == $selectedProduct->id) {
                $productAlreadyInCart = true;

                $product['quantity'] = (int) ($product['quantity'] ?? 0) + 1;

                $single = (float) ($product['singlePrice'] ?? $unitPrice);
                $product['singlePrice'] = $single;
                $product['price'] = $single * (int) $product['quantity'];
                $product['priceFormatted'] = CurrencyHelper::formatPrice($product['price']);
            }
        }

        if (! $productAlreadyInCart) {
            $products[] = [
                'id' => $selectedProduct->id,
                'identifier' => Str::random(),
                'name' => $selectedProduct->getTranslation('name', app()->getLocale()),
                'image' => mediaHelper()->getSingleMedia($selectedProduct->firstImage, ['widen' => 300])->url ?? '',
                'quantity' => 1,
                'singlePrice' => $unitPrice,
                'price' => $unitPrice,
                'priceFormatted' => CurrencyHelper::formatPrice($unitPrice),
                'vat_rate' => (float) ($selectedProduct->vat_rate ?? $selectedProduct->tax_rate ?? 21),
                'extra' => [],
            ];
        }

        $POSCart->products = array_values($products);
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

                return response()->json([
                    'products' => array_reverse($posCart->products ?? []),
                    'message' => 'Korting toegepast',
                    'discountCode' => $discountCode->code,
                    'success' => true,
                ]);
            }

            if (str($productSearchQuery)->startsWith('order-')) {
                $orderId = str($productSearchQuery)->replace('order-', '');
                $order = Order::find($orderId);
                if ($order) {
                    return response()->json([
                        'products' => array_reverse($posCart->products ?? []),
                        'success' => true,
                        'message' => 'Bestelling gevonden',
                        'order' => self::orderResponse($order),
                    ]);
                }
            }

            return response()->json([
                'products' => array_reverse($posCart->products ?? []),
                'message' => 'Product niet gevonden',
                'success' => false,
            ], 404);
        }

        $products = $this->addProductToCart($posCart, $selectedProduct);

        return response()->json([
            'products' => array_reverse($products ?? []),
            'success' => true,
        ]);
    }

    public function changeQuantity(Request $request)
    {
        $data = $request->all();

        $productIdentifier = $data['productIdentifier'] ?? null;
        $quantity = (int) ($data['quantity'] ?? 0);
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();
        $products = $posCart->products ?? [];

        if ($quantity < 1) {
            $products = collect($products)
                ->reject(fn ($product) => ($product['identifier'] ?? null) === $productIdentifier)
                ->values()
                ->toArray();
        } else {
            foreach ($products as &$product) {
                if (($product['identifier'] ?? null) == $productIdentifier) {
                    $product['quantity'] = $quantity;

                    $single = (float) ($product['singlePrice'] ?? 0);
                    $product['price'] = $single * $quantity;
                    $product['priceFormatted'] = CurrencyHelper::formatPrice($product['price']);
                }
            }
        }

        $posCart->products = array_values($products);
        $posCart->save();

        return response()->json([
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

        return response()->json([
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

        return response()->json(['success' => true]);
    }

    public function selectPaymentMethod(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $order = $data['order'] ?? null;
        if ($order) {
            $order = Order::find($order['id']);
        }

        $cartInstance = $data['cartInstance'] ?? null;
        $orderOrigin = $data['orderOrigin'] ?? null;
        $paymentMethodId = $data['paymentMethodId'] ?? null;
        $userId = $data['userId'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        if (! $order) {
            $response = $this->createOrder($cartInstance, $posCart, $paymentMethodId, $orderOrigin, $userId);
        }

        if ($order || ($response['success'] ?? false)) {
            $paymentMethod = PaymentMethod::find($paymentMethodId);

            if (! $order) {
                $order = $response['order'];
            }

            $suggestedCashPaymentAmounts = $this->getPaymentOptions($order->total);

            $isPinTerminalPayment = (bool) ($paymentMethod?->pinTerminal);
            $postPay = (bool) ($paymentMethod?->postpay);

            if ($postPay) {
                POSHelper::finishPaidOrder($order, $posCart, 'waiting_for_confirmation', 'unhandled');
            }

            return response()->json([
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
        }

        return response()->json([
            'success' => false,
            'message' => $response['message'] ?? 'Er ging iets mis bij het aanmaken van de bestelling',
        ], 500);
    }

    public function selectShippingMethod(Request $request)
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $shippingMethod = ShippingMethod::find($data['shippingMethodId']);

        if (! $shippingMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Verzendmethode niet gevonden',
            ], 500);
        }

        $posCart->shipping_method_id = $shippingMethod->id;
        $posCart->save();

        return response()->json([
            'success' => true,
            'shippingMethodId' => $shippingMethod->id,
            'shippingCosts' => CurrencyHelper::formatPrice(
                $shippingMethod->costsForCart(optional(ShoppingCart::getShippingZoneByCountry($posCart->country))->id)
            ),
        ]);
    }

    public function removeShippingMethod(Request $request)
    {
        $data = $request->all();
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();
        $posCart->shipping_method_id = null;
        $posCart->save();

        return response()->json(['success' => true]);
    }

    public function updateCustomerData(Request $request)
    {
        $data = $request->all();
        $posIdentifier = $data['posIdentifier'] ?? null;

        $posCart = POSCart::where('identifier', $posIdentifier)->first();
        $posCart->save();

        return response()->json(['success' => true]);
    }

    public function createOrder($cartInstance, $posCart, $paymentMethodId, $orderOrigin, $userId): array
    {
        $posCart = POSCart::where('identifier', $posCart->identifier)->first();

        $products = $posCart->products ?? [];
        if (! count($products)) {
            return [
                'success' => false,
                'message' => Translation::get('no-items-in-cart', 'cart', 'Je hebt geen producten in je winkelwagen'),
            ];
        }

        $shippingMethod = ShippingMethod::find($posCart->shipping_method_id);

        $discountCodeModel = null;
        if ($posCart->discount_code) {
            $discountCodeModel = DiscountCode::usable()->where('code', $posCart->discount_code)->first();

            if (! $discountCodeModel) {
                $posCart->discount_code = '';
                $posCart->save();
            }
        }

        $totals = $this->calculatePosCartTotals($posCart, $discountCodeModel);

        $shippingCosts = (float) ($shippingMethod ? $shippingMethod->costsForCart(
            optional(ShoppingCart::getShippingZoneByCountry($posCart->country ?: Countries::getAllSelectedCountries()[0]))->id
        ) : 0);

        $total = (float) ($totals['subtotal'] ?? 0) + $shippingCosts;

        $loadedConceptId = $posCart->loaded_concept_order_id ?? null;
        $order = null;
        if ($orderOrigin === 'pos' && $loadedConceptId) {
            $order = Order::concept()->find($loadedConceptId);
            if ($order) {
                // Hard-delete the concept's order products (including soft-deleted ghosts). The
                // orderProducts() relation uses withTrashed(), so a regular ->delete() would leave
                // soft-deleted rows visible alongside the new ones and duplicate every line on the
                // finalised order/invoice.
                $order->orderProducts()->withTrashed()->forceDelete();
                // Finalising a concept means the order is being placed now - reset the
                // created_at so reports and the invoice show the real order moment.
                $order->created_at = now();
                $posCart->loaded_concept_order_id = null;
                $posCart->save();
            }
        }
        if (! $order) {
            $order = new Order();
        }
        $order->order_origin = $orderOrigin;

        $order->first_name = $posCart->first_name;
        $order->last_name = $posCart->last_name;
        $order->email = $posCart->email;
        $order->user_id = $posCart->customer_user_id;

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

        $order->subtotal = (float) ($totals['subtotal'] ?? 0);
        $order->discount = (float) ($totals['discount'] ?? 0);
        $order->btw = (float) ($totals['vat'] ?? 0);
        $order->vat_percentages = (array) ($totals['vatPercentages'] ?? []);
        $order->total = $total;

        $order->status = 'pending';
        $order->ga_user_id = null;

        if ($discountCodeModel) {
            $order->discount_code_id = $discountCodeModel->id;
        }

        $order->shipping_method_id = $shippingMethod->id ?? null;
        $order->prices_ex_vat = (bool) ($posCart->prices_ex_vat ?? false);
        // Clear concept-only snapshot data now that the order is being finalised.
        $order->concept_cart_snapshot = null;
        $order->concept_discount_code = null;
        $order->save();

        $extraOptionCache = [];
        $extraCache = [];

        foreach (($totals['lines'] ?? []) as $line) {
            $orderProduct = new OrderProduct();
            $orderProduct->quantity = (int) $line['quantity'];
            $orderProduct->product_id = $line['product_id'] ? (int) $line['product_id'] : null;
            $orderProduct->order_id = $order->id;
            $orderProduct->name = $line['name'];
            $orderProduct->sku = null;
            $orderProduct->vat_rate = $line['vat_rate'] ?? 21;
            $orderProduct->price = (float) $line['line_total'];
            $orderProduct->discount = (float) $line['discount'];

            $productExtras = [];
            $originalPosItem = collect($posCart->products ?? [])
                ->first(fn ($p) => ($p['identifier'] ?? null) === ($line['source_identifier'] ?? null));

            foreach (($originalPosItem['extra'] ?? []) as $productExtraId => $productExtraOptionId) {
                if (! $productExtraOptionId) {
                    continue;
                }

                if (! isset($extraOptionCache[$productExtraOptionId])) {
                    $extraOptionCache[$productExtraOptionId] = ProductExtraOption::find($productExtraOptionId);
                }
                $opt = $extraOptionCache[$productExtraOptionId];

                if (! isset($extraCache[$productExtraId])) {
                    $extraCache[$productExtraId] = ProductExtra::find($productExtraId);
                }
                $extra = $extraCache[$productExtraId];

                $productExtras[] = [
                    'id' => $productExtraOptionId,
                    'name' => $extra?->name ?? ($opt?->productExtra?->name ?? ''),
                    'value' => $opt?->value ?? '',
                    'price' => (float) ($opt?->price ?? 0),
                ];
            }

            $orderProduct->product_extras = $productExtras;
            $orderProduct->save();
        }

        if ($shippingCosts > 0) {
            $shippingLine = new OrderProduct();
            $shippingLine->quantity = 1;
            $shippingLine->product_id = null;
            $shippingLine->order_id = $order->id;
            $shippingLine->name = $shippingMethod?->name ?? 'Verzendkosten';
            $shippingLine->price = $shippingCosts;
            $shippingLine->btw = $this->calculateVatFromGross($shippingCosts, 21);
            $shippingLine->vat_rate = 21;
            $shippingLine->discount = 0;
            $shippingLine->product_extras = [];
            $shippingLine->sku = 'shipping_costs';
            $shippingLine->save();
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

        return response()->json($pinTerminalResponse, $pinTerminalResponse['success'] ? 200 : 400);
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
                return response()->json(['success' => false, 'message' => 'Geen bedrag ingevoerd'], 400);
            } elseif (! $hasMultiplePayments && $cashPaymentAmount < $order->total) {
                return response()->json(['success' => false, 'message' => 'Bedrag is te laag'], 400);
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
                return response()->json([
                    'success' => false,
                    'message' => 'Geen pin terminal gevonden, bestelling incorrect afgehandeld',
                ], 400);
            }

            return response()->json([
                'success' => true,
                'order' => $order,
                'startPinTerminalPayment' => true,
                'paymentMethod' => [
                    'id' => $paymentMethod->id,
                    'name' => $paymentMethod->name,
                    'image' => mediaHelper()->getSingleMedia($paymentMethod->image, ['widen' => 300])->url ?? '',
                ],
            ]);
        }

        POSHelper::finishPaidOrder($order, $posCart);

        $order->totalFormatted = CurrencyHelper::formatPrice($order->total);
        $order->paidAmount = $order->paidAmount;
        $order->paidAmountFormatted = CurrencyHelper::formatPrice($order->paidAmount);
        $order->changeMoney = CurrencyHelper::formatPrice($orderPayment->amount - $order->total);
        $order->shouldChangeMoney = $orderPayment->amount > $order->total;

        $orderPayments = $order->orderPayments;
        foreach ($orderPayments as $op) {
            $op->amountFormatted = CurrencyHelper::formatPrice($op->amount);
            $op->paymentMethodName = $op->paymentMethod->name;
        }

        return response()->json([
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
            foreach ($orderPayments as $op) {
                $op->amountFormatted = CurrencyHelper::formatPrice($op->amount);
                $op->paymentMethodName = $op->paymentMethod->name;
            }

            return response()->json([
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
        }

        if ($order->status == 'cancelled') {
            return response()->json([
                'success' => true,
                'pinTerminalStatus' => 'cancelled_by_customer',
                'pinTerminalError' => true,
                'pinTerminalErrorMessage' => 'Betaling geannuleerd door klant',
            ]);
        }

        return response()->json([
            'success' => true,
            'pinTerminalStatus' => 'pending',
            'pinTerminalError' => false,
            'pinTerminalErrorMessage' => null,
        ]);
    }

    public function closePayment(Request $request): JsonResponse
    {
        $data = $request->all();

        $posIdentifier = $data['posIdentifier'] ?? null;
        $order = $data['order'] ?? null;

        if (! $order || ! isset($order['id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Geen bestelling gevonden',
            ], 400);
        }

        $order = Order::find($order['id']);
        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        if ($order->isPaidFor()) {
            return response()->json([
                'success' => false,
                'message' => 'Bestelling is al betaald',
            ]);
        }

        $order->changeStatus('cancelled');

        return response()->json([
            'success' => true,
            'message' => 'De bestelling is geannuleerd',
        ]);
    }

    public function getAllProducts(Request $request): JsonResponse
    {
        $clearCache = $request->get('clearCache', false);
        if ($clearCache) {
            Cache::forget('pos_products');
        }

        $products = Product::handOrderShowable()
            ->select(['id', 'name', 'images', 'price', 'ean', 'sku', 'current_price', 'discount_price', 'use_stock', 'stock', 'stock_status'])
            ->get()
            ->map(function ($product) {
                $name = $product->getTranslation('name', app()->getLocale());
                $currentPrice = $product->currentPrice;

                return [
                    'id' => $product->id,
                    'name' => $name,
                    'stock' => $product->directSellableStock(),
                    'actual_stock' => $product->stock,
                    'currentPrice' => $currentPrice,
                    'currentPriceFormatted' => CurrencyHelper::formatPrice($currentPrice),
                    'search' => $name.' '.$product->sku.' '.$product->ean,
                ];
            })
            ->toArray();

        return response()->json([
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

        return response()->json([
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

        return response()->json(['success' => true]);
    }

    public function updateSearchQueryInputmode(Request $request): JsonResponse
    {
        $data = $request->all();

        $searchQueryInputmode = $data['searchQueryInputmode'] ?? null;
        Customsetting::set('pos_search_query_inputmode', $searchQueryInputmode);

        return response()->json(['success' => true]);
    }

    public function cancelOrder(Request $request): JsonResponse
    {
        $data = $request->all();

        $order = $data['order'] ?? null;
        $cancelData = $order['cancelData'] ?? null;

        $order = Order::find($order['id']);

        if (in_array($order->order_origin, ['own', 'pos']) && $order->invoice_id != 'PROFORMA') {
            $sendCustomerEmail = $cancelData['sendCustomerEmail'];
            $productsMustBeReturned = $cancelData['productsMustBeReturned'];
            $restock = $cancelData['restock'];
            $refundDiscountCosts = $cancelData['refundDiscountCosts'];

            $cancelledProductsQuantity = 0;
            $orderProducts = $cancelData['orderProducts'];
            foreach ($orderProducts as $orderProduct) {
                $cancelledProductsQuantity += $orderProduct['refundQuantity'];
            }

            $extraOrderLine = $cancelData['extraOrderLine'];
            $extraOrderLineName = $cancelData['extraOrderLineName'] ?? '';
            $extraOrderLinePrice = $cancelData['extraOrderLinePrice'] ?? '';

            if (! $extraOrderLine && $cancelledProductsQuantity == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Geen producten geretourneerd',
                ]);
            }

            $order->markAsCancelledWithCredit(
                $sendCustomerEmail,
                $productsMustBeReturned,
                $restock,
                $refundDiscountCosts,
                $extraOrderLineName,
                $extraOrderLinePrice,
                collect($orderProducts),
                $cancelData['fulfillmentStatus'],
                $cancelData['paymentMethodId']
            );

            if (Customsetting::get('pos_auto_print_receipt', null, true)) {
                try {
                    $order->printReceipt();
                } catch (\Exception $e) {
                }
            }

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => true]);
    }

    public function retrieveOrders(Request $request): JsonResponse
    {
        $data = $request->all();

        $skip = $data['skip'] ?? null;
        $searchOrderQuery = $data['searchOrderQuery'] ?? null;

        if ($searchOrderQuery && str($searchOrderQuery)->startsWith('order-')) {
            $orderId = str($searchOrderQuery)->replace('order-', '');
            $order = Order::find($orderId);
            if ($order) {
                return response()->json([
                    'success' => true,
                    'order' => self::orderResponse($order),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Bestelling niet gevonden',
            ]);
        }

        $endDate = now()->subQuarter()->startOfDay();

        $orders = Order::orderBy('created_at', 'desc');

        if (! $searchOrderQuery) {
            $orders->where('created_at', '>=', $endDate);
        } else {
            $orders->quickSearch($searchOrderQuery);
        }

        $orders = $orders
            ->skip($skip)
            ->limit(50)
            ->get()
            ->groupBy(fn ($order) => $order->created_at->format('Y-m-d'))
            ->map(function ($orders, $date) {
                return [
                    'date' => Carbon::parse($date)->isToday()
                        ? 'Vandaag'
                        : (Carbon::parse($date)->isYesterday() ? 'Gisteren' : Carbon::parse($date)->format('d M')),
                    'orders' => $orders->map(fn ($order) => self::orderResponse($order)),
                ];
            })
            ->values()
            ->toArray();

        $firstOrder = null;
        foreach ($orders as $date) {
            foreach ($date['orders'] as $order) {
                $firstOrder ??= $order;
            }
        }

        return response()->json([
            'success' => true,
            'orders' => $orders,
            'firstOrder' => $firstOrder,
        ]);
    }

    public function orderResponse(Order $order): array
    {
        $fulfillmentStatusOptions = Orders::getFulfillmentStatusses();
        $paymentMethods = PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->pluck('name', 'id')->toArray();
        $paymentMethodId = PaymentMethod::whereIn('type', ['pos', 'online'])->where('psp', 'own')->where('is_cash_payment', 1)->first()->id ?? null;

        $vatPercentages = $order->vat_percentages ?? [];
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

        return [
            'id' => $order->id,
            'invoiceId' => $order->invoice_id,
            'createdAt' => $order->created_at->format('d-m-Y H:i'),
            'hasOpenAmount' => $order->openAmount > 0.00,
            'openAmount' => $order->openAmount,
            'openAmountFormatted' => CurrencyHelper::formatPrice($order->openAmount),
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
        ];
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

            return response()->json(
                $this->updateCart($cartInstance, $posIdentifier, $discountCode)
            );
        }

        return response()->json([
            'success' => false,
            'message' => 'Geen actieve winkelwagen gevonden voor deze klant',
        ], 404);
    }

    private function normalizePosCartItem(array $chosenProduct): object
    {
        $options = $chosenProduct['options'] ?? [];
        $options['options'] = $options['options'] ?? [];

        foreach (($chosenProduct['extra'] ?? []) as $productExtraId => $productExtraOptionId) {
            if (! $productExtraOptionId) {
                continue;
            }

            $options['options'][(string) $productExtraOptionId] = [
                'quantity' => (int) ($chosenProduct['quantity'] ?? 1),
            ];
        }

        if (! empty($chosenProduct['customProduct'])) {
            $options['customProduct'] = true;
        }
        if (! empty($chosenProduct['isCustomPrice'])) {
            $options['isCustomPrice'] = true;
        }

        if (isset($chosenProduct['singlePrice'])) {
            $options['singlePrice'] = (float) $chosenProduct['singlePrice'];
        }

        if (isset($chosenProduct['vat_rate'])) {
            $options['vat_rate'] = $chosenProduct['vat_rate'];
        } elseif (isset($chosenProduct['vatPercentage'])) {
            $options['vat_rate'] = $chosenProduct['vatPercentage'];
        }

        return (object) [
            'qty' => max(1, (int) ($chosenProduct['quantity'] ?? 1)),
            'quantity' => max(1, (int) ($chosenProduct['quantity'] ?? 1)),
            'product_id' => $chosenProduct['id'] ?? null,
            'name' => $chosenProduct['name'] ?? null,
            'price' => $chosenProduct['price'] ?? null,
            'options' => $options,
            'model' => null,
        ];
    }

    private function calculateVatFromGross(float $gross, float $vatRate): float
    {
        if ($vatRate <= 0) {
            return 0.0;
        }

        $divider = 1 + ($vatRate / 100);
        if ($divider <= 0) {
            return 0.0;
        }

        return $gross - ($gross / $divider);
    }

    public function calculatePosCartTotals(POSCart $posCart, ?DiscountCode $discountCodeModel = null): array
    {
        $products = $posCart->products ?? [];
        $customerUser = $posCart->customer_user_id ? User::find($posCart->customer_user_id) : null;

        $lines = [];
        $subtotal = 0.0;
        $subtotalWithoutDiscount = 0.0;
        $vatTotal = 0.0;
        $vatPercentages = [];

        foreach ($products as $chosenProduct) {
            $qty = (int) ($chosenProduct['quantity'] ?? 0);
            if ($qty < 1) {
                continue;
            }

            $item = $this->normalizePosCartItem($chosenProduct);

            $model = null;
            if (! empty($item->product_id) && is_numeric($item->product_id)) {
                $model = Product::with(['volumeDiscounts', 'productCategories'])->find((int) $item->product_id);
            }
            $item->model = $model;

            if ($customerUser && $model) {
                $options = (array) ($item->options ?? []);
                $options['singlePrice'] = (float) $model->priceForUser($customerUser);
                $options['isCustomPrice'] = true;
                $item->options = $options;
            }

            $lineWithoutDiscount = (float) Product::getShoppingCartItemPrice($item, null);
            $lineWithDiscount = $lineWithoutDiscount;

            if ($discountCodeModel && ($discountCodeModel->type ?? null) === 'percentage') {
                $lineWithDiscount = (float) Product::getShoppingCartItemPrice($item, $discountCodeModel);
            }

            $subtotal += $lineWithDiscount;
            $subtotalWithoutDiscount += $lineWithoutDiscount;

            $vatRate = (float) ($item->options['vat_rate'] ?? ($model->vat_rate ?? $model->tax_rate ?? 21));
            $lineVat = $this->calculateVatFromGross($lineWithDiscount, $vatRate);

            $vatTotal += $lineVat;
            $vatPercentages[(string) $vatRate] = ($vatPercentages[(string) $vatRate] ?? 0) + $lineVat;

            $lines[] = [
                'source_identifier' => $chosenProduct['identifier'] ?? Str::random(),
                'product_id' => $item->product_id,
                'name' => $chosenProduct['name'] ?? ($model?->getTranslation('name', app()->getLocale()) ?? $item->name),
                'quantity' => $qty,
                'vat_rate' => $vatRate,
                'line_total' => round($lineWithDiscount, 2),
                'line_total_without_discount' => round($lineWithoutDiscount, 2),
                'discount' => round(max(0, $lineWithoutDiscount - $lineWithDiscount), 2),
                'is_custom' => (bool) ($chosenProduct['customProduct'] ?? false) || (bool) ($chosenProduct['isCustomPrice'] ?? false) || ! $model,
            ];
        }

        $discount = max(0, $subtotalWithoutDiscount - $subtotal);

        if ($discountCodeModel && ($discountCodeModel->type ?? null) === 'amount') {
            $fixedDiscount = min($subtotal, (float) ($discountCodeModel->discount_amount ?? 0));

            if ($fixedDiscount > 0 && $subtotal > 0 && count($lines)) {
                $baseSubtotalForDistribution = $subtotal;

                foreach ($lines as $index => &$line) {
                    $ratio = $baseSubtotalForDistribution > 0 ? ($line['line_total'] / $baseSubtotalForDistribution) : 0;
                    $lineDiscountPart = round($fixedDiscount * $ratio, 2);

                    $line['discount'] = round($line['discount'] + $lineDiscountPart, 2);
                    $line['line_total'] = round(max(0, $line['line_total'] - $lineDiscountPart), 2);
                }
                unset($line);

                $distributedTotal = round(array_sum(array_column($lines, 'line_total')), 2);
                $expectedSubtotal = round($subtotal - $fixedDiscount, 2);
                $roundingDifference = round($expectedSubtotal - $distributedTotal, 2);

                if ($roundingDifference != 0.0) {
                    $lastIndex = array_key_last($lines);
                    $lines[$lastIndex]['line_total'] = round(max(0, $lines[$lastIndex]['line_total'] + $roundingDifference), 2);
                    $lines[$lastIndex]['discount'] = round(max(0, $lines[$lastIndex]['line_total_without_discount'] - $lines[$lastIndex]['line_total']), 2);
                }

                $subtotal = round($expectedSubtotal, 2);
                $discount = round($discount + $fixedDiscount, 2);

                $vatTotal = 0.0;
                $vatPercentages = [];

                foreach ($lines as $line) {
                    $lineVat = $this->calculateVatFromGross((float) $line['line_total'], (float) $line['vat_rate']);
                    $vatTotal += $lineVat;
                    $vatPercentages[(string) $line['vat_rate']] = ($vatPercentages[(string) $line['vat_rate']] ?? 0) + $lineVat;
                }
            }
        }

        $vatPercentages = collect($vatPercentages)
            ->map(fn ($value) => round((float) $value, 2))
            ->toArray();

        return [
            'lines' => $lines,
            'subtotal' => round(max(0, $subtotal), 2),
            'subtotalWithoutDiscount' => round(max(0, $subtotalWithoutDiscount), 2),
            'discount' => round(max(0, $discount), 2),
            'vat' => round(max(0, $vatTotal), 2),
            'vatPercentages' => $vatPercentages,
        ];
    }
}
