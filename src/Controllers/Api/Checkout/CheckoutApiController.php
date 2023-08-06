<?php

namespace Dashed\DashedEcommerceCore\Controllers\Api\Checkout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

class CheckoutApiController extends Controller
{
    public function availableShippingMethods(Request $request)
    {
        if (! $request->country) {
            $shippingMethods = [];
        } else {
            $allShippingMethods = ShoppingCart::getAvailableShippingMethods($request->country, true);
            $shippingMethods = [];
            foreach ($allShippingMethods as $allShippingMethod) {
                $shippingMethods[] = $allShippingMethod;
            }
        }

        return response()->json([
            'shippingMethods' => $shippingMethods,
        ]);
    }

    public function availablePaymentMethods(Request $request)
    {
        return response()->json([
            'paymentMethods' => $request->country ? ShoppingCart::getAvailablePaymentMethods($request->country, true) : [],
        ]);
    }

    public function getCheckoutData(Request $request)
    {
        return response()->json(
            ShoppingCart::getCheckoutData($request->shippingMethod, $request->paymentMethod),
        );
    }
}
