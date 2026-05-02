<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedEcommerceCore\Middleware\GoogleAdsBasicAuth;
use Dashed\DashedEcommerceCore\Controllers\CustomerMatchController;

Route::middleware(['throttle:google-ads-customer-match', GoogleAdsBasicAuth::class])
    ->group(function () {
        Route::get('/google-ads/customer-match/{slug}.csv', [CustomerMatchController::class, 'export'])
            ->where('slug', '[A-Za-z0-9]+')
            ->name('google-ads.customer-match.export');
    });
