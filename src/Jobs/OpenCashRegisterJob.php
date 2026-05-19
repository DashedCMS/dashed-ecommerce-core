<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Classes\PinTerminal;

class OpenCashRegisterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function handle(): void
    {
        try {
            PinTerminal::openCashRegister();
        } catch (Throwable $e) {
            Log::warning('pos: cash register kon niet geopend worden', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
