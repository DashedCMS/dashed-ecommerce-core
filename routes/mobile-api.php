<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OrderController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ShipmentController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\InsightsController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ProductInsightsController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\PrinterController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\CustomerController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ProductController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ProductGroupController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OpenOrderProductController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\PosConceptController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\DashboardTargetsController;
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
    'apply-custom-discount' => 'applyCustomDiscount',
    'set-prices-ex-vat' => 'setPricesExVat',
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
        Route::post('products', [ProductController::class, 'store'])->middleware('ability:products.write');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('ability:products.read');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('ability:products.write');

        Route::get('open-order-products', [OpenOrderProductController::class, 'index'])->middleware('ability:orders.read');

        Route::get('insights/products', [ProductInsightsController::class, 'index'])->middleware('ability:dashboard.read');
        Route::get('insights', [InsightsController::class, 'index'])->middleware('ability:dashboard.read');

        // Per-site dashboard-doelen (omzet/bestellingen) per periode instellen.
        // Een management-actie → gegate met de admin-write ability die de
        // ecommerce-routes voor schrijfacties gebruiken.
        Route::put('dashboard/targets', [DashboardTargetsController::class, 'update'])->middleware('ability:orders.write');

        Route::get('customers', [CustomerController::class, 'index'])->middleware('ability:orders.read');
        Route::get('customers/profile', [CustomerController::class, 'profile'])->middleware('ability:orders.read');

        // Verzend-hub: één overzicht van alle zendingen over de carriers heen.
        Route::get('shipments', [ShipmentController::class, 'index'])->middleware('ability:orders.read');

        Route::get('orders', [OrderController::class, 'index'])->middleware('ability:orders.read');
        Route::get('orders/filter-options', [OrderController::class, 'filterOptions'])->middleware('ability:orders.read');
        Route::get('orders/match', [OrderController::class, 'match'])->middleware('ability:orders.read');

        // Bulk-acties — vóór de orders/{order}-wildcard, anders wordt 'bulk' als
        // order-id opgevangen. Zelfde recht (orders.write) als de single-writes.
        Route::post('orders/bulk/status', [OrderController::class, 'bulkStatus'])->middleware('ability:orders.write');
        Route::post('orders/bulk/fulfillment', [OrderController::class, 'bulkFulfillment'])->middleware('ability:orders.write');
        Route::post('orders/bulk/create-label', [OrderController::class, 'bulkCreateLabel'])->middleware('ability:orders.write');

        Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('ability:orders.read');
        Route::patch('orders/{order}', [OrderController::class, 'update'])->middleware('ability:orders.write');
        Route::post('orders/{order}/mark-as-paid', [OrderController::class, 'markAsPaid'])->middleware('ability:orders.write');
        Route::post('orders/{order}/fulfillment', [OrderController::class, 'changeFulfillment'])->middleware('ability:orders.write');
        Route::post('orders/{order}/return', [OrderController::class, 'returnOrder'])->middleware('ability:orders.write');
        Route::post('orders/{order}/return-label', [ShipmentController::class, 'returnLabel'])->middleware('ability:orders.write');
        Route::post('orders/{order}/packed', [OrderController::class, 'packed'])->middleware('ability:orders.write');
        Route::get('orders/{order}/invoice-url', [OrderController::class, 'invoiceUrl'])->middleware('ability:orders.read');
        Route::get('orders/{order}/packing-slip-url', [OrderController::class, 'packingSlipUrl'])->middleware('ability:orders.read');
        Route::get('orders/{order}/label-url', [OrderController::class, 'labelUrl'])->middleware('ability:orders.read');
        Route::get('orders/{order}/labels', [OrderController::class, 'labels'])->middleware('ability:orders.read');
        Route::get('orders/{order}/label-options', [OrderController::class, 'labelOptions'])->middleware('ability:orders.read');
        Route::post('orders/{order}/create-label', [OrderController::class, 'createLabel'])->middleware('ability:orders.write');
        Route::post('orders/{order}/notes', [OrderController::class, 'addNote'])->middleware('ability:orders.write');
        Route::post('orders/{order}/print', [OrderController::class, 'print'])->middleware('ability:orders.write');
        Route::post('orders/{order}/print-documents', [OrderController::class, 'printDocuments'])->middleware('ability:orders.write');
        Route::get('orders/{order}/actions', [OrderController::class, 'actions'])->middleware('ability:orders.read');
        Route::post('orders/{order}/actions/{key}', [OrderController::class, 'runAction'])->middleware('ability:orders.write');

        // Printerbeheer (netwerk-printers voor pakbon/label). Printen zelf loopt via
        // de print-queue + de daemon op de Pi/NAS.
        Route::get('printers', [PrinterController::class, 'index'])->middleware('ability:orders.write');
        Route::post('printers', [PrinterController::class, 'store'])->middleware('ability:orders.write');
        Route::patch('printers/{printer}', [PrinterController::class, 'update'])->middleware('ability:orders.write');
        Route::delete('printers/{printer}', [PrinterController::class, 'destroy'])->middleware('ability:orders.write');
        Route::post('printers/{printer}/pair', [PrinterController::class, 'pair'])->middleware('ability:orders.write');
    });
