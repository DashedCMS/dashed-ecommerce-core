<?php

use Dashed\DashedCore\Middleware\AdminMiddleware;
use Dashed\DashedCore\Middleware\AuthMiddleware;
use Dashed\DashedCore\Middleware\FrontendMiddleware;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Controllers\Api\Checkout\CheckoutApiController;
use Dashed\DashedEcommerceCore\Controllers\Frontend\AccountController;
use Dashed\DashedEcommerceCore\Controllers\Frontend\CartController;
use Dashed\DashedEcommerceCore\Controllers\Frontend\TransactionController;
use Dashed\DashedTranslations\Models\Translation;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;

//Exchange routes
Route::get('/ecommerce/orders/exchange', [TransactionController::class, 'exchange'])->name('dashed.frontend.checkout.exchange');
Route::post('/ecommerce/orders/exchange', [TransactionController::class, 'exchange'])->name('dashed.frontend.checkout.exchange.post');
Route::get('/dashed/exchange', [TransactionController::class, 'exchange'])->name('dashed.frontend.checkout.dashed.exchange');
Route::post('/dashed/exchange', [TransactionController::class, 'exchange'])->name('dashed.frontend.checkout.dashed.exchange.post');

Route::group(
    [
        'prefix' => LaravelLocalization::setLocale(),
        'middleware' => ['web', FrontendMiddleware::class, LocaleSessionRedirect::class, LaravelLocalizationRedirectFilter::class, LaravelLocalizationViewPath::class],
    ],
    function () {
        if (Customsetting::get('checkout_account') != 'disabled') {
            Route::group([
                'middleware' => [AuthMiddleware::class],
            ], function () {
                //Account routes
                Route::prefix('/' . Translation::get('account-slug', 'slug', 'account'))->group(function () {
                    Route::get('/' . Translation::get('account-orders-slug', 'slug', 'orders'), [AccountController::class, 'orders'])->name('dashed.frontend.account.orders');
                });
            });
        }

        //Cart routes
        Route::get('/' . Translation::get('cart-slug', 'slug', 'cart'), [CartController::class, 'cart'])->name('dashed.frontend.cart');
        Route::get('/' . Translation::get('checkout-slug', 'slug', 'checkout'), [CartController::class, 'checkout'])->name('dashed.frontend.checkout');
        Route::post('/' . Translation::get('checkout-slug', 'slug', 'checkout'), [TransactionController::class, 'startTransaction'])->name('dashed.frontend.start-transaction');
        Route::get('/' . Translation::get('complete-order-slug', 'slug', 'complete'), [TransactionController::class, 'complete'])->name('dashed.frontend.checkout.complete');
        Route::get('/download-invoice/{orderHash}', [CartController::class, 'downloadInvoice'])->name('dashed.frontend.download-invoice');
        Route::get('/download-packing-slip/{orderHash}', [CartController::class, 'downloadPackingSlip'])->name('dashed.frontend.download-packing-slip');
        Route::post('/apply-discount-code', [CartController::class, 'applyDiscountCode'])->name('dashed.frontend.cart.apply-discount-code');
        Route::post('/add-to-cart/{product}', [CartController::class, 'addToCart'])->name('dashed.frontend.cart.add-to-cart');
        Route::post('/update-to-cart/{rowId}', [CartController::class, 'updateToCart'])->name('dashed.frontend.cart.update-to-cart');
        Route::post('/remove-from-cart/{rowId}', [CartController::class, 'removeFromCart'])->name('dashed.frontend.cart.remove-from-cart');
    }
);

Route::middleware(['web'])->prefix(config('filament.path', 'dashed') . '/api')->group(function () {
    Route::get('/checkout/available-shipping-methods', [CheckoutApiController::class, 'availableShippingMethods'])->name('dashed.api.checkout.available-shipping-methods');
    Route::get('/checkout/available-payment-methods', [CheckoutApiController::class, 'availablePaymentMethods'])->name('dashed.api.checkout.available-payment-methods');
    Route::get('/checkout/get-checkout-data', [CheckoutApiController::class, 'getCheckoutData'])->name('dashed.api.checkout.get-checkout-data');
});

Route::middleware(['web', AdminMiddleware::class])->group(function () {
    Route::get('/ecommerce/point-of-sale', function(){
        return view('dashed-ecommerce-core::pos.pages.point-of-sale-wrapper');
    })->name('dashed.ecommerce.point-of-sale');
    Route::get('/ecommerce/customer-point-of-sale', function(){
        return view('dashed-ecommerce-core::pos.pages.customer-point-of-sale-wrapper');
    })->name('dashed.ecommerce.point-of-sale');
});
