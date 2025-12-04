<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;

Route::group(
    [
        'prefix' => 'api/point-of-sale',
        'middleware' => [], //Todo: add protection from unauthorized access
    ],
    function () {
        Route::post('/open-cash-register', [PointOfSaleApiController::class, 'openCashRegister'])
            ->name('api.point-of-sale.open-cash-register');
        Route::post('/initialize', [PointOfSaleApiController::class, 'initialize'])
            ->name('api.point-of-sale.initialize');
        Route::post('/update-cart', [PointOfSaleApiController::class, 'retrieveCart'])
            ->name('api.point-of-sale.retrieve-cart');
        Route::post('/retrieve-cart-for-customer', [PointOfSaleApiController::class, 'insertOrderInPOSCart'])
            ->name('api.point-of-sale.insert-order-in-pos-cart');
        Route::post('/retrieve-cart-for-customer', [PointOfSaleApiController::class, 'retrieveCartForCustomer'])
            ->name('api.point-of-sale.retrieve-cart-for-customer');
        Route::post('/print-receipt', [PointOfSaleApiController::class, 'printReceipt'])
            ->name('api.point-of-sale.print-receipt');
        Route::post('/send-invoice', [PointOfSaleApiController::class, 'sendInvoice'])
            ->name('api.point-of-sale.send-invoice');
        Route::post('/search-products', [PointOfSaleApiController::class, 'searchProducts'])
            ->name('api.point-of-sale.search-products');
        Route::post('/add-product', [PointOfSaleApiController::class, 'addProduct'])
            ->name('api.point-of-sale.add-product');
        Route::post('/update-product', [PointOfSaleApiController::class, 'updateProduct'])
            ->name('api.point-of-sale.update-product');
        Route::post('/select-product', [PointOfSaleApiController::class, 'selectProduct'])
            ->name('api.point-of-sale.select-product');
        Route::post('/change-quantity', [PointOfSaleApiController::class, 'changeQuantity'])
            ->name('api.point-of-sale.change-quantity');
        Route::post('/clear-products', [PointOfSaleApiController::class, 'clearProducts'])
            ->name('api.point-of-sale.clear-products');
        Route::post('/remove-discount', [PointOfSaleApiController::class, 'removeDiscount'])
            ->name('api.point-of-sale.remove-discount');
        Route::post('/select-payment-method', [PointOfSaleApiController::class, 'selectPaymentMethod'])
            ->name('api.point-of-sale.select-payment-method');
        Route::post('/select-shipping-method', [PointOfSaleApiController::class, 'selectShippingMethod'])
            ->name('api.point-of-sale.select-shipping-method');
        Route::post('/remove-shipping-method', [PointOfSaleApiController::class, 'removeShippingMethod'])
            ->name('api.point-of-sale.remove-shipping-method');
        Route::post('/update-customer-data', [PointOfSaleApiController::class, 'updateCustomerData'])
            ->name('api.point-of-sale.update-customer-data');
        Route::post('/start-pin-terminal-payment', [PointOfSaleApiController::class, 'startPinTerminalPayment'])
            ->name('api.point-of-sale.start-pin-terminal-payment');
        Route::post('/mark-as-paid', [PointOfSaleApiController::class, 'markAsPaid'])
            ->name('api.point-of-sale.mark-as-paid');
        Route::post('/check-pin-terminal-payment', [PointOfSaleApiController::class, 'checkPinTerminalPayment'])
            ->name('api.point-of-sale.check-pin-terminal-payment');
        Route::post('/close-payment', [PointOfSaleApiController::class, 'closePayment'])
            ->name('api.point-of-sale.close-payment');
        Route::post('/get-all-products', [PointOfSaleApiController::class, 'getAllProducts'])
            ->name('api.point-of-sale.get-all-products');
        Route::post('/api.point-of-sale.update-product-info', [PointOfSaleApiController::class, 'updateProductInfo'])
            ->name('api.point-of-sale.update-product-info');
        Route::post('/update-search-query-input-mode', [PointOfSaleApiController::class, 'updateSearchQueryInputmode'])
            ->name('api.point-of-sale.update-search-query-input-mode');
        Route::post('/retrieve-orders', [PointOfSaleApiController::class, 'retrieveOrders'])
            ->name('api.point-of-sale.retrieve-orders');
        Route::post('/cancel-order', [PointOfSaleApiController::class, 'cancelOrder'])
            ->name('api.point-of-sale.cancel-order');
    }
);
