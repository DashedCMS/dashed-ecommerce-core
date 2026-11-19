<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\FrontendMiddleware;
use Dashed\DashedEcommerceCore\Controllers\Frontend\ReorderController;
use Dashed\DashedEcommerceCore\Controllers\Frontend\ReferralController;

// "Bestel opnieuw" uit het account en de ordermails. Ondertekend en op de
// orderhash; de controller zelf toetst of de functie aan staat en of de
// bestelling te herhalen is (geen Bol, geen creditorder).
Route::get('/reorder/{orderHash}', ReorderController::class)
    ->middleware(['web', FrontendMiddleware::class, 'signed', 'throttle:dashed-cart'])
    ->name('dashed.frontend.reorder');

// De gedeelde verwijslink van een klant.
Route::get('/verwijzing/{code}', ReferralController::class)
    ->where('code', '[A-Za-z0-9\-]{4,40}')
    ->middleware(['web', FrontendMiddleware::class, 'throttle:dashed-cart'])
    ->name('dashed.frontend.referral');
