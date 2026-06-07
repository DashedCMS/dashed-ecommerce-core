<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands\PrintQueue;

use Illuminate\Console\Command;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Notifications\PrintQueue\PrinterOfflineNotification;

class PrinterHealthCheckCommand extends Command
{
    protected $signature = 'print-queue:health-check';

    protected $description = 'Stuur offline notificatie voor printers die >5 min niet hebben gepingd';

    public function handle(): int
    {
        $threshold = now()->subMinutes(5);

        $offlinePrinters = Printer::active()
            ->where(fn ($q) => $q->whereNull('last_ping_at')->orWhere('last_ping_at', '<', $threshold))
            ->get();

        foreach ($offlinePrinters as $printer) {
            $cacheKey = "print-queue:printer-offline-notified:{$printer->ulid}";
            if (Cache::has($cacheKey)) {
                continue;
            }

            $admins = $this->resolveAdmins();
            if ($admins->isEmpty()) {
                Cache::put($cacheKey, true, now()->addMinutes(30));

                continue;
            }

            Notification::send($admins, new PrinterOfflineNotification($printer));
            Cache::put($cacheKey, true, now()->addMinutes(30));
        }

        return self::SUCCESS;
    }

    private function resolveAdmins(): \Illuminate\Support\Collection
    {
        $query = User::query();

        if (method_exists(User::class, 'role')) {
            return $query->role('admin')->get();
        }

        return $query->get();
    }
}
