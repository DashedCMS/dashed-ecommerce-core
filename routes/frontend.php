<?php

use Illuminate\Support\Facades\Route;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Middleware\AuthMiddleware;
use Qubiqx\QcommerceTranslations\Models\Translation;
use Qubiqx\QcommerceCore\Middleware\FrontendMiddleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Qubiqx\QcommerceEcommerceCore\Controllers\Frontend\CartController;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Qubiqx\QcommerceEcommerceCore\Controllers\Frontend\AccountController;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Qubiqx\QcommerceEcommerceCore\Controllers\Frontend\TransactionController;
use Qubiqx\QcommerceEcommerceCore\Controllers\Api\Checkout\CheckoutApiController;

if (!app()->runningInConsole()) {
    //Exchange routes
    Route::get('/' . config('filament.path') . '/exchange', [TransactionController::class, 'exchange'])->name('qcommerce.frontend.checkout.exchange');
    Route::post('/' . config('filament.path') . '/exchange', [TransactionController::class, 'exchange'])->name('qcommerce.frontend.checkout.exchange');
}

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
                    Route::get('/' . Translation::get('account-orders-slug', 'slug', 'orders'), [AccountController::class, 'orders'])->name('qcommerce.frontend.account.orders');
                });
            });
        }

        //Cart routes
        Route::get('/' . Translation::get('cart-slug', 'slug', 'cart'), [CartController::class, 'cart'])->name('qcommerce.frontend.cart');
        Route::get('/' . Translation::get('checkout-slug', 'slug', 'checkout'), [CartController::class, 'checkout'])->name('qcommerce.frontend.checkout');
        Route::post('/' . Translation::get('checkout-slug', 'slug', 'checkout'), [TransactionController::class, 'startTransaction'])->name('qcommerce.frontend.start-transaction');
        Route::get('/' . Translation::get('complete-order-slug', 'slug', 'complete'), [TransactionController::class, 'complete'])->name('qcommerce.frontend.checkout.complete');
        Route::get('/download-invoice/{orderHash}', [CartController::class, 'downloadInvoice'])->name('qcommerce.frontend.download-invoice');
        Route::get('/download-packing-slip/{orderHash}', [CartController::class, 'downloadPackingSlip'])->name('qcommerce.frontend.download-packing-slip');
        Route::post('/apply-discount-code', [CartController::class, 'applyDiscountCode'])->name('qcommerce.frontend.cart.apply-discount-code');
        Route::post('/add-to-cart/{product}', [CartController::class, 'addToCart'])->name('qcommerce.frontend.cart.add-to-cart');
        Route::post('/update-to-cart/{rowId}', [CartController::class, 'updateToCart'])->name('qcommerce.frontend.cart.update-to-cart');
        Route::post('/remove-from-cart/{rowId}', [CartController::class, 'removeFromCart'])->name('qcommerce.frontend.cart.remove-from-cart');
    }
);

Route::middleware(['web'])->prefix(config('filament.path') . '/api')->group(function () {
    Route::get('/checkout/available-shipping-methods', [CheckoutApiController::class, 'availableShippingMethods'])->name('qcommerce.api.checkout.available-shipping-methods');
    Route::get('/checkout/available-payment-methods', [CheckoutApiController::class, 'availablePaymentMethods'])->name('qcommerce.api.checkout.available-payment-methods');
    Route::get('/checkout/get-checkout-data', [CheckoutApiController::class, 'getCheckoutData'])->name('qcommerce.api.checkout.get-checkout-data');
});
