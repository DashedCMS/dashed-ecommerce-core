<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Http\Controllers\Api\PrintQueueController;
use Dashed\DashedEcommerceCore\Models\Printer;
use Illuminate\Support\Facades\Route;

Route::get('/printer-install-discover/{nonce}', function (string $nonce) {
    $script = view('dashed-ecommerce-core::print.install-script-discover', [
        'apiUrl' => rtrim(url('/'), '/'),
        'discoverUrl' => \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'dashed.print-queue.discover',
            now()->addHours(24),
            ['nonce' => $nonce],
        ),
    ])->render();

    return response($script, 200, [
        'Content-Type' => 'text/x-shellscript; charset=utf-8',
        'Content-Disposition' => 'inline; filename="dashedcms-printer-discover.sh"',
        'Cache-Control' => 'no-store',
    ]);
})->middleware('signed')->name('dashed.print-queue.installer-discover');

Route::post('/api/print/discover/{nonce}', function (string $nonce, \Illuminate\Http\Request $request) {
    $data = $request->validate([
        'hostname' => ['nullable', 'string', 'max:120'],
        'discovered_printers' => ['required', 'array', 'min:1'],
        'discovered_printers.*' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
    ]);

    $hostname = $data['hostname'] ?? null;
    $created = [];
    $skipped = [];

    foreach ($data['discovered_printers'] as $cupsName) {
        $existing = Printer::where('cups_name', $cupsName)->first();

        if ($existing) {
            $skipped[] = [
                'cups_name' => $cupsName,
                'reason' => 'cups_name bestaat al in CMS als printer "' . $existing->name . '"',
            ];
            continue;
        }

        $printer = Printer::create([
            'name' => $cupsName . ($hostname ? ' (' . $hostname . ')' : ''),
            'cups_name' => $cupsName,
            'hostname' => $hostname,
            'type' => \Dashed\DashedEcommerceCore\Enums\PrinterType::PackingSlip,
            'is_active' => true,
        ]);

        $token = $printer->createToken("printer-{$printer->ulid}")->plainTextToken;
        $printer->forceFill(['plain_token' => $token])->save();

        $created[] = [
            'cups_name' => $cupsName,
            'ulid' => $printer->ulid,
            'token' => $token,
        ];
    }

    return response()->json([
        'created' => $created,
        'skipped' => $skipped,
    ]);
})->middleware('signed')->name('dashed.print-queue.discover');

Route::get('/printer-install/{ulid}', function (string $ulid) {
    $printer = Printer::where('ulid', $ulid)->firstOrFail();

    if (! $printer->plain_token) {
        abort(409, 'Deze printer heeft nog geen token. Genereer er eerst een in admin.');
    }

    $script = view('dashed-ecommerce-core::print.install-script', [
        'apiUrl' => rtrim(url('/'), '/'),
        'token' => $printer->plain_token,
        'cupsName' => $printer->cups_name ?: 'CHANGE_ME',
        'printerName' => $printer->name,
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
