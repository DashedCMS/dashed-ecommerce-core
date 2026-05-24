<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Http\Controllers\Api\PrintQueueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'ensure.printer'])
    ->prefix('api/print')
    ->name('dashed.print-queue.')
    ->group(function (): void {
        Route::get('pending', [PrintQueueController::class, 'pending'])->name('pending');
        Route::post('ping', [PrintQueueController::class, 'ping'])->name('ping');
        Route::post('{ulid}/claim', [PrintQueueController::class, 'claim'])->name('claim');
        Route::post('{ulid}/done', [PrintQueueController::class, 'done'])->name('done');
        Route::post('{ulid}/failed', [PrintQueueController::class, 'failed'])->name('failed');
        Route::get('{ulid}/pdf', [PrintQueueController::class, 'pdf'])->name('pdf');
    });
