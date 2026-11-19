<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Throwable;
use Illuminate\Console\Command;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Satisfaction\Satisfaction;
use Dashed\DashedEcommerceCore\Classes\Satisfaction\DeliverySatisfaction;

/**
 * Stuurt per site met "Vraag na levering" aan de tevredenheidsmail naar de
 * bestellingen die aan de beurt zijn. Elke dag één keer.
 */
class SendDeliverySatisfactionCommand extends Command
{
    protected $signature = 'dashed:send-delivery-satisfaction';

    protected $description = 'Ask customers how satisfied they are, a few days after delivery';

    public static function hasWork(): bool
    {
        return rescue(fn () => collect(Sites::getSites())->contains(
            fn ($site) => Satisfaction::enabled(Satisfaction::SOURCE_DELIVERY, $site['id'])
        ), false, false);
    }

    public function handle(): int
    {
        if (! class_exists(Satisfaction::class)) {
            return self::SUCCESS;
        }

        $sent = 0;
        foreach (Sites::getSites() as $site) {
            if (! Satisfaction::enabled(Satisfaction::SOURCE_DELIVERY, $site['id'])) {
                continue;
            }

            DeliverySatisfaction::dueOrders($site['id'])->orderBy('id')->chunkById(200, function ($orders) use (&$sent) {
                foreach ($orders as $order) {
                    try {
                        $sent += DeliverySatisfaction::send($order) ? 1 : 0;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });
        }

        $this->info('Sent: '.$sent);

        return self::SUCCESS;
    }
}
