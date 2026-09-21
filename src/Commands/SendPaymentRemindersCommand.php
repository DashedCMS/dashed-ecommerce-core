<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Services\OnAccount\PaymentReminderSender;

class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'dashed:send-payment-reminders';

    protected $description = 'Stuur betaalherinneringen voor vervallen facturen op rekening';

    public function handle(): int
    {
        $this->info(PaymentReminderSender::run().' herinneringen verstuurd');

        return self::SUCCESS;
    }
}
