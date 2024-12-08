<?php

use Dashed\DashedCore\Middleware\AdminMiddleware;
use Dashed\DashedCore\Middleware\AuthMiddleware;
use Dashed\DashedCore\Middleware\FrontendMiddleware;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Controllers\Api\PointOfSale\PointOfSaleApiController;
use Dashed\DashedEcommerceCore\Controllers\Frontend\AccountController;
use Dashed\DashedEcommerceCore\Controllers\Frontend\CartController;
use Dashed\DashedEcommerceCore\Controllers\Frontend\TransactionController;
use Dashed\DashedEcommerceCore\Middleware\HandleInertiaRequests;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Support\Facades\Route;
use Dashed\LaravelLocalization\Facades\LaravelLocalization;
use Dashed\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Dashed\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Dashed\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Inertia\Inertia;

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
        Route::post('/print-receipt', [PointOfSaleApiController::class, 'printReceipt'])
            ->name('api.point-of-sale.print-receipt');
        Route::post('/search-products', [PointOfSaleApiController::class, 'searchProducts'])
            ->name('api.point-of-sale.search-products');
        Route::post('/add-product', [PointOfSaleApiController::class, 'addProduct'])
            ->name('api.point-of-sale.add-product');
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
        Route::post('/start-pin-terminal-payment', [PointOfSaleApiController::class, 'startPinTerminalPayment'])
            ->name('api.point-of-sale.start-pin-terminal-payment');
        Route::post('/mark-as-paid', [PointOfSaleApiController::class, 'markAsPaid'])
            ->name('api.point-of-sale.mark-as-paid');
        Route::post('/check-pin-terminal-payment', [PointOfSaleApiController::class, 'checkPinTerminalPayment'])
            ->name('api.point-of-sale.check-pin-terminal-payment');
        Route::post('/close-payment', [PointOfSaleApiController::class, 'closePayment'])
            ->name('api.point-of-sale.close-payment');
    }
);
