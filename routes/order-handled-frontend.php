<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedEcommerceCore\Controllers\OrderHandledClickController;
use Dashed\DashedEcommerceCore\Controllers\OrderHandledUnsubscribeController;

Route::middleware(['web'])->group(function () {
    Route::get('/order-handled/click/{order}/{step}', [OrderHandledClickController::class, 'click'])
        ->name('dashed.frontend.order-handled.click')
        ->middleware('signed');

    Route::get('/order-handled/unsubscribe/{order}', [OrderHandledUnsubscribeController::class, 'unsubscribe'])
        ->name('dashed.frontend.order-handled.unsubscribe')
        ->middleware('signed');
});
