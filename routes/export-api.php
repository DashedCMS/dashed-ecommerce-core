<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedEcommerceCore\Models\ApiClient;
use Dashed\DashedEcommerceCore\Http\Middleware\AuthenticateApiClient;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\Export\OrderExportController;

/**
 * Export-API voor externe partijen, met een sleutel uit Instellingen →
 * API-koppelingen (Bearer). Los van de mobiele API: een ApiClient-sleutel
 * werkt niet op auth:sanctum-routes en andersom.
 */
Route::prefix('api/v1/export')
    ->middleware(['throttle:dashed-export-api', AuthenticateApiClient::class . ':' . ApiClient::ABILITY_ORDERS_EXPORT])
    ->name('dashed.export-api.')
    ->group(function (): void {
        Route::get('orders', [OrderExportController::class, 'index'])->name('orders');
        Route::get('orders/daily', [OrderExportController::class, 'daily'])->name('orders.daily');
    });
