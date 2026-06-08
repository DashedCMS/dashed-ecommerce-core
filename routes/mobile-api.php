<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OrderController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ProductController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ProductGroupController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OpenOrderProductController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\PosConceptController;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

/**
 * Mobiele kassa: dezelfde PointOfSaleApiController als de web-kassa, maar onder
 * Sanctum + mobile.site + het `pos.use`-recht (de web-routes blijven ongemoeid).
 *
 * @var array<string, string> $posActions
 */
$posActions = [
    'initialize' => 'initialize',
    'update-cart' => 'retrieveCart',
    'get-all-products' => 'getAllProducts',
    'search-products' => 'searchProducts',
    'add-product' => 'addProduct',
    'add-custom-product' => 'addCustomProduct',
    'select-product' => 'selectProduct',
    'update-product' => 'updateProduct',
    'update-product-info' => 'updateProductInfo',
    'change-quantity' => 'changeQuantity',
    'clear-products' => 'clearProducts',
    'remove-discount' => 'removeDiscount',
    'update-customer-data' => 'updateCustomerData',
    'select-shipping-method' => 'selectShippingMethod',
    'remove-shipping-method' => 'removeShippingMethod',
    'select-payment-method' => 'selectPaymentMethod',
    'mark-as-paid' => 'markAsPaid',
    'start-pin-terminal-payment' => 'startPinTerminalPayment',
    'check-pin-terminal-payment' => 'checkPinTerminalPayment',
    'close-payment' => 'closePayment',
    'open-cash-register' => 'openCashRegister',
    'print-receipt' => 'printReceipt',
    'send-invoice' => 'sendInvoice',
    'send-payment-link' => 'sendPaymentLink',
    'retrieve-orders' => 'retrieveOrders',
    'retrieve-cart-for-customer' => 'retrieveCartForCustomer',
    'insert-order-in-pos-cart' => 'insertOrderInPOSCart',
    'cancel-order' => 'cancelOrder',
    'update-search-query-input-mode' => 'updateSearchQueryInputmode',
];

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function () use ($posActions): void {
        Route::prefix('point-of-sale')->middleware('ability:pos.use')->group(function () use ($posActions): void {
            foreach ($posActions as $path => $action) {
                Route::post($path, [PointOfSaleApiController::class, $action]);
            }

            // Concept-orders ("parkeren") — eigen controller die de ConceptOrderService hergebruikt.
            Route::get('concepts', [PosConceptController::class, 'index']);
            Route::post('save-concept', [PosConceptController::class, 'save']);
            Route::post('load-concept', [PosConceptController::class, 'load']);
        });

        Route::get('product-categories', [ProductGroupController::class, 'categories'])->middleware('ability:products.read');
        Route::get('product-groups', [ProductGroupController::class, 'index'])->middleware('ability:products.read');
        Route::get('product-groups/{productGroup}', [ProductGroupController::class, 'show'])->middleware('ability:products.read');

        Route::get('products', [ProductController::class, 'index'])->middleware('ability:products.read');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('ability:products.read');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('ability:products.write');

        Route::get('open-order-products', [OpenOrderProductController::class, 'index'])->middleware('ability:orders.read');

        Route::get('orders', [OrderController::class, 'index'])->middleware('ability:orders.read');
        Route::get('orders/filter-options', [OrderController::class, 'filterOptions'])->middleware('ability:orders.read');
        Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('ability:orders.read');
        Route::patch('orders/{order}', [OrderController::class, 'update'])->middleware('ability:orders.write');
    });
