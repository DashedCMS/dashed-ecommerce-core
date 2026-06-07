<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\OrderController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ProductController;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\ProductGroupController;

Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'mobile.site'])
    ->group(function (): void {
        Route::get('product-groups', [ProductGroupController::class, 'index'])->middleware('ability:products.read');
        Route::get('product-groups/{productGroup}', [ProductGroupController::class, 'show'])->middleware('ability:products.read');

        Route::get('products', [ProductController::class, 'index'])->middleware('ability:products.read');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('ability:products.read');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('ability:products.write');

        Route::get('orders', [OrderController::class, 'index'])->middleware('ability:orders.read');
        Route::get('orders/filter-options', [OrderController::class, 'filterOptions'])->middleware('ability:orders.read');
        Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('ability:orders.read');
        Route::patch('orders/{order}', [OrderController::class, 'update'])->middleware('ability:orders.write');
    });
