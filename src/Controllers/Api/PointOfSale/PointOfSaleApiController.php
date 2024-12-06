<?php

namespace Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Dashed\ReceiptPrinter\ReceiptPrinter;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\ProductExtra;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

class PointOfSaleApiController extends Controller
{
    public function openCashRegister(Request $request)
    {
        try {
            $printer = new ReceiptPrinter();
            $printer->init(
                Customsetting::get('receipt_printer_connector_type'),
                Customsetting::get('receipt_printer_connector_descriptor')
            );
            $printer->openDrawer();
            $printer->close();

            return response()
                ->json([
                    'success' => true,
                ]);
        } catch (\Exception $e) {
            return response()
                ->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
        }
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

        //Todo: only add fields you need
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

    public function retrieveCart(Request $request)
    {
        $data = $request->all();

        $cartInstance = $data['cartInstance'] ?? [];
        $posIdentifier = $data['posIdentifier'] ?? [];

        ShoppingCart::setInstance($cartInstance);
        ShoppingCart::emptyMyCart();

        $posCart = POSCart::where('identifier', $posIdentifier)->first();

        $discountCode = $data['discountCode'] ?? $posCart->discount_code;

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

        //        $shippingMethods = ShoppingCart::getAvailableShippingMethods($this->country);
        //        $shippingMethod = '';
        //        foreach ($shippingMethods as $thisShippingMethod) {
        //            if ($thisShippingMethod['id'] == $this->shipping_method_id) {
        //                $shippingMethod = $thisShippingMethod;
        //            }
        //        }
        //
        //        if (!$shippingMethod) {
        //            $this->shipping_method_id = null;
        //        }

        $checkoutData = ShoppingCart::getCheckoutData($shippingMethodId ?? null, $paymentMethodId ?? null);


        //        $this->total = $checkoutData['total'];
        $discount = $checkoutData['discountFormatted'];
        $vat = $checkoutData['btwFormatted'];
        $vatPercentages = $checkoutData['btwPercentages'];
        foreach ($vatPercentages as $key => $value) {
            $vatPercentages[$key] = CurrencyHelper::formatPrice($value);
        }
        $subTotal = $checkoutData['subTotalFormatted'];
        $total = $checkoutData['totalFormatted'];

        $this->loading = false;

        return response()
            ->json([
                'products' => $products ?? [],
                'discountCode' => $discountCode ?? null,
                'activeDiscountCode' => $activeDiscountCode ?? null,
                'discount' => $discount ?? null,
                'vat' => $vat ?? null,
                'vatPercentages' => $vatPercentages ?? null,
                'subTotal' => $subTotal ?? null,
                'total' => $total ?? null,
                'success' => true,
            ]);
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
}
