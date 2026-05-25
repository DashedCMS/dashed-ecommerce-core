<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Http\Controllers\Api\PrintQueueController;
use Dashed\DashedEcommerceCore\Models\Printer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/printer-install/{ulid}', function (string $ulid) {
    $printer = Printer::where('ulid', $ulid)->firstOrFail();

    if (! $printer->plain_token) {
        abort(409, 'Deze printer heeft nog geen token. Genereer er eerst een in het admin paneel.');
    }

    $script = view('dashed-ecommerce-core::print.install-script', [
        'printerName' => $printer->name,
        'apiUrl' => rtrim(url('/'), '/'),
        'token' => $printer->plain_token,
        'cupsName' => Str::of($printer->name)->slug('_')->lower()->toString(),
    ])->render();

    return response($script, 200, [
        'Content-Type' => 'text/x-shellscript; charset=utf-8',
        'Content-Disposition' => 'inline; filename="install-dashedcms-printer.sh"',
        'Cache-Control' => 'no-store',
    ]);
})->middleware('signed')->name('dashed.print-queue.installer');

Route::get('/vendor/dashed-ecommerce-core/pi/{file}', function (string $file) {
    $allowed = [
        'print_daemon.py' => 'text/x-python',
        'dashedcms-printer.service' => 'text/plain',
        'config.example.yaml' => 'text/yaml',
        'requirements.txt' => 'text/plain',
        'README.md' => 'text/markdown',
    ];

    if (! isset($allowed[$file])) {
        abort(404);
    }

    $path = __DIR__ . '/../resources/pi/' . $file;
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => $allowed[$file],
        'Cache-Control' => 'public, max-age=300',
    ]);
})->where('file', '[A-Za-z0-9_.-]+')->name('dashed.print-queue.pi-asset');

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
