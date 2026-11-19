<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\FrontendMiddleware;
use Dashed\DashedEcommerceCore\Controllers\Frontend\ProductReviewController;

// Het reviewformulier achter de ondertekende link uit het reviewverzoek. Geen
// account nodig: de handtekening (met vervaldatum) is de toegang.
Route::middleware(['web'])->group(function () {
    Route::get('/product-review/{orderProduct}', [ProductReviewController::class, 'show'])
        ->middleware([FrontendMiddleware::class, 'signed', 'throttle:dashed-order-pages'])
        ->whereNumber('orderProduct')
        ->name('dashed.frontend.product-review');
});
