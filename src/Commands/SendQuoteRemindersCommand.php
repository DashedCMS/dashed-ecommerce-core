<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Quote;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteSender;
use Dashed\DashedEcommerceCore\Services\Quotes\QuoteDefaults;

class SendQuoteRemindersCommand extends Command
{
    protected $signature = 'dashed:send-quote-reminders';

    protected $description = 'Stuur een herinnering voor offertes die binnenkort verlopen';

    public function handle(): int
    {
        $sent = 0;

        $quotes = Quote::query()
            ->where('status', Quote::STATUS_SENT)
            ->whereNull('reminder_sent_at')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', today())
            ->get();

        foreach ($quotes as $quote) {
            // De instelling staat per site, dus per offerte lezen en niet een
            // keer bovenaan: een installatie met meerdere sites kan hem op de
            // ene aan en op de andere uit hebben staan.
            if (! QuoteDefaults::reminderEnabled($quote->site_id)) {
                continue;
            }

            $window = today()->addDays(QuoteDefaults::reminderDaysBefore($quote->site_id));

            if ($quote->valid_until->gt($window)) {
                continue;
            }

            QuoteSender::sendReminder($quote);
            $sent++;
        }

        $this->info($sent.' herinneringen verstuurd');

        return self::SUCCESS;
    }
}
