<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Controllers\CustomerMatchController;
use Dashed\DashedEcommerceCore\Middleware\GoogleAdsBasicAuth;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:google-ads-customer-match', GoogleAdsBasicAuth::class])
    ->group(function () {
        Route::get('/google-ads/customer-match/{slug}.csv', [CustomerMatchController::class, 'export'])
            ->where('slug', '[A-Za-z0-9]+')
            ->name('google-ads.customer-match.export');
    });
