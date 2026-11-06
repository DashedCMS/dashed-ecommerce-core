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

    /**
     * Snelle controle voor de scheduler: zonder werk start er geen eigen
     * artisan-proces. Een printer die al gemeld is, slaat handle() ook over.
     * Lukt de controle niet, dan draait het commando gewoon.
     */
    public static function hasWork(): bool
    {
        return rescue(
            fn () => self::offlinePrinters()
                ->pluck('ulid')
                ->contains(fn ($ulid) => ! Cache::has(self::notifiedKey($ulid))),
            true,
            false,
        );
    }

    private static function offlinePrinters()
    {
        $threshold = now()->subMinutes(5);

        return Printer::active()
            ->where(fn ($q) => $q->whereNull('last_ping_at')->orWhere('last_ping_at', '<', $threshold));
    }

    private static function notifiedKey(string $ulid): string
    {
        return "print-queue:printer-offline-notified:{$ulid}";
    }

    public function handle(): int
    {
        foreach (self::offlinePrinters()->get() as $printer) {
            $cacheKey = self::notifiedKey($printer->ulid);
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

    /**
     * `role` is een kolom op users en geen methode of scope. De eerdere
     * method_exists-check was daardoor altijd onwaar en viel terug op alle
     * gebruikers, klanten inbegrepen: bij één offline printer kreeg elke
     * webshopklant elk half uur een melding in de notifications-tabel.
     */
    private function resolveAdmins(): \Illuminate\Support\Collection
    {
        return User::query()->whereIn('role', ['admin', 'superadmin'])->get();
    }
}
