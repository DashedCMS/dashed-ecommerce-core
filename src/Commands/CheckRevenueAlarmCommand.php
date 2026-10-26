<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Classes\Alerts\RevenueAlarm;

class CheckRevenueAlarmCommand extends Command
{
    protected $signature = 'dashed:check-revenue-alarm';

    protected $description = 'Meld de beheerders als er duidelijk minder betaalde bestellingen binnenkomen dan normaal';

    public function handle(): int
    {
        $sent = RevenueAlarm::check();

        $this->info($sent.' omzetalarm(en) verstuurd');

        return self::SUCCESS;
    }
}
