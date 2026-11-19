<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Services\Referrals\ReferralProgram;

class ProcessReferralsCommand extends Command
{
    protected $signature = 'dashed:process-referrals';

    protected $description = 'Beloon verwijzers waarvan de bestelling de wachttijd zonder retour doorkwam';

    public function handle(): int
    {
        $counts = ReferralProgram::processDue();

        $this->info("Beloond: {$counts['rewarded']}, afgewezen: {$counts['rejected']}");

        return self::SUCCESS;
    }
}
