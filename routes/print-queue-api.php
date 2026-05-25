<?php

declare(strict_types=1);

use Dashed\DashedEcommerceCore\Http\Controllers\Api\PrintQueueController;
use Dashed\DashedEcommerceCore\Models\Printer;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/printer-install/pairing/{code}', function (string $code) {
    $printer = Printer::findByPairingCode($code);

    if (! $printer) {
        abort(404, 'Pairing code ongeldig of verlopen. Genereer een nieuwe in het admin paneel.');
    }

    $script = view('dashed-ecommerce-core::print.install-script', [
        'apiUrl' => rtrim(url('/'), '/'),
        'pairingCode' => $code,
    ])->render();

    return response($script, 200, [
        'Content-Type' => 'text/x-shellscript; charset=utf-8',
        'Content-Disposition' => 'inline; filename="install-dashedcms-printer.sh"',
        'Cache-Control' => 'no-store',
    ]);
})->middleware('signed')->name('dashed.print-queue.installer');

Route::get('/printer-install/pairing-docker/{code}', function (string $code) {
    $printer = Printer::findByPairingCode($code);

    if (! $printer) {
        abort(404, 'Pairing code ongeldig of verlopen. Genereer een nieuwe in het admin paneel.');
    }

    $script = view('dashed-ecommerce-core::print.install-script-docker', [
        'apiUrl' => rtrim(url('/'), '/'),
        'pairingCode' => $code,
    ])->render();

    return response($script, 200, [
        'Content-Type' => 'text/x-shellscript; charset=utf-8',
        'Content-Disposition' => 'inline; filename="install-dashedcms-printer-docker.sh"',
        'Cache-Control' => 'no-store',
    ]);
})->middleware('signed')->name('dashed.print-queue.installer-docker');

Route::post('/api/print/pair', function (\Illuminate\Http\Request $request) {
    $data = $request->validate([
        'pairing_code' => ['required', 'string', 'max:32'],
        'hostname' => ['nullable', 'string', 'max:120'],
        'discovered_printers' => ['array'],
        'discovered_printers.*.cups_name' => ['required', 'string', 'max:80'],
        'discovered_printers.*.device_uri' => ['nullable', 'string', 'max:200'],
        'discovered_printers.*.make_and_model' => ['nullable', 'string', 'max:200'],
    ]);

    $printer = Printer::findByPairingCode($data['pairing_code']);

    if (! $printer) {
        return response()->json(['message' => 'Pairing code ongeldig of verlopen.'], 422);
    }

    $printer->tokens()->delete();
    $token = $printer->createToken("printer-{$printer->ulid}")->plainTextToken;

    $discovered = $data['discovered_printers'] ?? [];
    $firstCupsName = $discovered[0]['cups_name'] ?? null;

    $hostnameLabel = $data['hostname'] ?? $printer->name;

    $printer->forceFill([
        'name' => $hostnameLabel,
        'hostname' => $data['hostname'] ?? null,
        'cups_printers' => $discovered,
        'cups_name' => $printer->cups_name ?: $firstCupsName,
        'plain_token' => $token,
        'is_active' => true,
        'paired_at' => now(),
        'pairing_code' => null,
        'pairing_expires_at' => null,
    ])->save();

    return response()->json([
        'token' => $token,
        'printer_ulid' => $printer->ulid,
        'cups_name' => $printer->cups_name,
        'message' => 'Printer succesvol gepaird met ' . count($discovered) . ' CUPS-printer(s).',
    ]);
})->name('dashed.print-queue.pair');

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

Route::get('/vendor/dashed-ecommerce-core/pi/docker/{file}', function (string $file) {
    $allowed = [
        'Dockerfile' => 'text/plain',
        'entrypoint.sh' => 'text/x-shellscript',
    ];

    if (! isset($allowed[$file])) {
        abort(404);
    }

    $path = __DIR__ . '/../resources/pi/docker/' . $file;
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => $allowed[$file],
        'Cache-Control' => 'public, max-age=300',
    ]);
})->where('file', '[A-Za-z0-9_.-]+')->name('dashed.print-queue.pi-docker-asset');

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
        Route::post('sync-printers', function (\Illuminate\Http\Request $request) {
            $printer = $request->attributes->get('printer');

            $data = $request->validate([
                'discovered_printers' => ['array'],
                'discovered_printers.*.cups_name' => ['required', 'string', 'max:80'],
                'discovered_printers.*.device_uri' => ['nullable', 'string', 'max:200'],
                'discovered_printers.*.make_and_model' => ['nullable', 'string', 'max:200'],
                'hostname' => ['nullable', 'string', 'max:120'],
            ]);

            $printer->forceFill([
                'cups_printers' => $data['discovered_printers'] ?? [],
                'hostname' => $data['hostname'] ?? $printer->hostname,
            ])->save();

            return response()->json(['count' => count($data['discovered_printers'] ?? [])]);
        })->name('sync-printers');
    });
