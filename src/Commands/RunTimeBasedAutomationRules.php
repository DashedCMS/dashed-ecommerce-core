<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationEngine;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationContext;
use Dashed\DashedEcommerceCore\Support\Automation\StockRuleScanner;
use Dashed\DashedEcommerceCore\Support\Automation\ConditionEvaluator;
use Dashed\DashedEcommerceCore\Support\Automation\TimeRuleScanner;

/**
 * Uurlijkse scan voor scan-gebaseerde automatiseringsregels: geen event, dus
 * geen AutomationTriggerSubscriber die de kandidaten aanlevert. Dit commando
 * speelt zelf die rol voor twee triggerfamilies:
 *  - tijd-regels (B2, `time.relative`/`time.recurring`) — scanTimeRules();
 *  - voorraad-regels (B3 task 6, `stock.low`/`stock.back`) — scanStockRules().
 * Beide volgen hetzelfde patroon: kandidaten via een scanner (TimeRuleScanner
 * resp. StockRuleScanner, die zelf al dedup/reset + site-scoping regelen),
 * voorwaarden op vuurmoment via ConditionEvaluator, uitvoering via
 * AutomationEngine (die zelf al claim-before-execute, lus-beveiliging en
 * automatable-handhaving regelt).
 *
 * Site-bewustheid zit al in de scanners (die filteren per regel op
 * `$rule->site_id`), dus dit commando hoeft zelf niet over sites te loopen.
 */
class RunTimeBasedAutomationRules extends Command
{
    protected $signature = 'dashed:run-time-automations';

    protected $description = 'Vuurt scan-gebaseerde automatiseringsregels (tijd- en voorraad-triggers) die nu verschuldigd zijn.';

    public function handle(): int
    {
        $this->scanTimeRules();
        $this->scanStockRules();

        return self::SUCCESS;
    }

    /**
     * Tijd-regels (B2): herkenbaar aan een gezette `schedule`-kolom (die
     * bestaat alleen voor tijd-triggers). `schedule.mode` kiest de scanner.
     */
    private function scanTimeRules(): void
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
    }

    /**
     * Voorraad-regels (B3 task 6): er is geen `schedule`-kolom om op te
     * filteren (voorraad-triggers hebben geen schedule-subformulier), dus we
     * herkennen een stock-regel via de trigger-registry i.p.v. een hardcoded
     * `whereIn('trigger', ['stock.low', 'stock.back'])` — zo blijft dit
     * commando automatisch kloppen als er ooit een derde `stock.*`-trigger
     * bijkomt die StockAutomationTriggers (of een ander package) registreert
     * met `type => 'stock'`, zonder dat dit commando zelf hoeft te weten
     * welke keys dat precies zijn.
     *
     * ec-core vereist dashed-mobile-api niet (soft dependency): de
     * trigger-registry woont dáár. Zonder dat package bestaat de class niet,
     * en dit commando draait uurlijks via de scheduler — een ongeguarde
     * app(MobileApiRegistry::class) zou het dus op élke tick laten crashen,
     * ook als er nul voorraad-regels zijn. Zelfde class_exists-guard als
     * AutomationRuleResource::registry(), RuleDryRun::registry() en
     * AutomationTriggerSubscriber::subscribe(): geen mobile-api → geen
     * triggers → niets om te scannen.
     */
    private function scanStockRules(): void
    {
        if (! class_exists(MobileApiRegistry::class)) {
            return;
        }

        $registry = app(MobileApiRegistry::class);

        $stockTriggerKeys = collect($registry->automationTriggers())
            ->filter(fn (array $trigger): bool => ($trigger['type'] ?? null) === 'stock')
            ->keys();

        if ($stockTriggerKeys->isEmpty()) {
            return;
        }

        AutomationRule::query()
            ->where('is_active', true)
            ->whereIn('trigger', $stockTriggerKeys)
            ->get()
            ->each(function (AutomationRule $rule): void {
                $candidates = match ($rule->trigger) {
                    'stock.low' => StockRuleScanner::lowCandidates($rule),
                    'stock.back' => StockRuleScanner::backCandidates($rule),
                    default => collect(),
                };

                foreach ($candidates as $product) {
                    $context = AutomationContext::forProduct($product);

                    if (ConditionEvaluator::matches($rule->conditions ?? [], $context)) {
                        AutomationEngine::run($rule, $product);
                    }
                }
            });
    }
}
