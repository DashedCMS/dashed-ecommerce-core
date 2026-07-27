<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationEngine;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationContext;
use Dashed\DashedEcommerceCore\Support\Automation\ConditionEvaluator;
use Dashed\DashedEcommerceCore\Support\Automation\TimeRuleScanner;

/**
 * Uurlijkse scan voor tijd-gebaseerde automatiseringsregels (B2): geen
 * event, dus geen AutomationTriggerSubscriber die de kandidaten aanlevert.
 * Dit commando speelt zelf die rol voor `time.relative`/`time.recurring`
 * regels — kandidaten via TimeRuleScanner, voorwaarden via
 * ConditionEvaluator, uitvoering via AutomationEngine (die zelf al
 * claim-before-execute, lus-beveiliging en automatable-handhaving regelt).
 *
 * Site-bewustheid zit al in TimeRuleScanner (die filtert per regel op
 * `$rule->site_id`), dus dit commando hoeft zelf niet over sites te loopen.
 */
class RunTimeBasedAutomationRules extends Command
{
    protected $signature = 'dashed:run-time-automations';

    protected $description = 'Vuurt tijd-gebaseerde automatiseringsregels (B2) die nu verschuldigd zijn.';

    public function handle(): int
    {
        $now = Carbon::now();

        AutomationRule::query()
            ->where('is_active', true)
            ->whereNotNull('schedule')
            ->get()
            ->each(function (AutomationRule $rule) use ($now): void {
                $mode = $rule->schedule['mode'] ?? null;

                $candidates = match ($mode) {
                    'relative' => TimeRuleScanner::relativeCandidates($rule, $now),
                    'recurring' => TimeRuleScanner::recurringCandidates($rule, $now),
                    default => collect(),
                };

                foreach ($candidates as $order) {
                    $context = AutomationContext::forOrder($order);

                    if (ConditionEvaluator::matches($rule->conditions ?? [], $context)) {
                        AutomationEngine::run($rule, $order);
                    }
                }
            });

        return self::SUCCESS;
    }
}
