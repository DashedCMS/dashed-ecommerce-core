<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedMobileApi\MobileApiRegistry;

/**
 * Tijd-triggers (B2): geen event, maar een uurlijkse scan
 * (RunTimeBasedAutomationRules). `type => 'time'` + `mode` sturen zowel het
 * Filament-schedule-subformulier als het scan-commando. Subject = Order; de
 * voorwaarden gelden op de order, dus dezelfde velden als de order-events.
 */
class TimeAutomationTriggers
{
    public static function register(MobileApiRegistry $registry): void
    {
        if (! method_exists($registry, 'registerAutomationTriggers')) {
            return;
        }

        $registry->registerAutomationTriggers([
            [
                'key' => 'time.relative',
                'label' => 'Op tijd na een moment',
                'type' => 'time',
                'mode' => 'relative',
                'subject' => 'order',
                'fields' => OrderAutomationTriggers::orderConditionFields(),
            ],
            [
                'key' => 'time.recurring',
                'label' => 'Terugkerend op een tijdstip',
                'type' => 'time',
                'mode' => 'recurring',
                'subject' => 'order',
                'fields' => OrderAutomationTriggers::orderConditionFields(),
            ],
        ]);
    }
}
