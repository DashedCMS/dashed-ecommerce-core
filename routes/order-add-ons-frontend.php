<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\FrontendMiddleware;
use Dashed\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Dashed\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Dashed\DashedEcommerceCore\Controllers\Frontend\OrderAddOnController;
use Dashed\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;

// Bijbestellen ("Nog iets vergeten?"): ondertekende link, zonder taalprefix zodat al gemailde links
// blijven werken. De handtekening controleert de controller zelf (een gesloten aanbod is geen 403).
Route::get('/bijbestellen/{order}', [OrderAddOnController::class, 'show'])
    ->whereNumber('order')
    ->middleware([
        'web',
        FrontendMiddleware::class,
        LocaleSessionRedirect::class,
        LaravelLocalizationRedirectFilter::class,
        LaravelLocalizationViewPath::class,
    ])
    ->name('dashed.frontend.order-add-ons.show');
