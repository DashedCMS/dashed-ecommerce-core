<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Classes\Alerts\RevenueAlarm;

class CheckRevenueAlarmCommand extends Command
{
    protected $signature = 'dashed:check-revenue-alarm';

    protected $description = 'Meld de beheerders als er duidelijk minder betaalde bestellingen binnenkomen dan normaal';

    /**
     * Snelle controle voor de scheduler: zonder werk start er geen eigen
     * artisan-proces. Lukt de controle niet, dan draait het commando gewoon.
     */
    public static function hasWork(): bool
    {
        return rescue(
            fn () => collect(Sites::getSites())->contains(fn ($site) => RevenueAlarm::enabled((string) $site['id'])),
            true,
            false,
        );
    }

    public function handle(): int
    {
        $sent = RevenueAlarm::check();

        $this->info($sent.' omzetalarm(en) verstuurd');

        return self::SUCCESS;
    }
}
