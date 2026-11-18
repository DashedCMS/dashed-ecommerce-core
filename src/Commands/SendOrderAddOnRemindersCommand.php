<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\ReminderPlanning;

class SendOrderAddOnRemindersCommand extends Command
{
    protected $signature = 'dashed:order-add-ons-mails {--force : also outside production}';

    protected $description = 'Queue the "Nog iets vergeten?" add-on reminder mail for orders paid the configured delay ago.';

    public function handle(ReminderPlanning $planning): int
    {
        // Lokaal staan vaak echte mailgegevens: daar nooit vanzelf klanten mailen.
        if (! app()->environment('production') && ! $this->option('force')) {
            $this->info('Only in production (or with --force).');

            return self::SUCCESS;
        }

        $this->info('Queued: '.$planning->plan());

        return self::SUCCESS;
    }
}
