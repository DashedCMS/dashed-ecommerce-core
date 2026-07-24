<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Listeners\Automation;

use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Jobs\RunAutomationRuleJob;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationEngine;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationContext;
use Dashed\DashedEcommerceCore\Support\Automation\ConditionEvaluator;

/**
 * Luistert bij boot dynamisch op alle event-classes die
 * MobileApiRegistry::automationTriggers() registreert — niet hardcoded, dus
 * een nieuwe trigger in OrderAutomationTriggers hoeft hier niets bij te
 * schrijven. Per event: onderwerp resolven via de trigger's `resolve`,
 * waardecontext bouwen, actieve regels voor deze trigger+site ophalen via
 * AutomationEngine::rulesFor() en per match (ConditionEvaluator::matches())
 * een RunAutomationRuleJob dispatchen op de 'ecommerce'-queue.
 */
class AutomationTriggerSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        // ec-core vereist dashed-mobile-api niet (soft dependency): de
        // trigger-registry woont dáár. Zonder dat package bestaat de class
        // niet en zou app(MobileApiRegistry::class) de boot laten crashen —
        // deze subscriber draait bij élke boot. Dezelfde class_exists-guard
        // als AutomationRuleResource::registry() en de ServiceProvider-
        // registratie: geen mobile-api → geen triggers → niets te luisteren.
        if (! class_exists(MobileApiRegistry::class)) {
            return;
        }

        $registry = app(MobileApiRegistry::class);

        foreach ($registry->automationTriggers() as $trigger) {
            $events->listen($trigger['event'], function (object $event) use ($trigger): void {
                $this->handle($trigger, $event);
            });
        }
    }

    /** @param  array<string, mixed>  $trigger */
    private function handle(array $trigger, object $event): void
    {
        // Laag 1 van de lus-beveiliging: zolang een regel z'n eigen acties
        // draait, slaat de subscriber alles over. Dekt events die in
        // hetzelfde proces ontstaan (bv. een actie die de fulfillment-
        // status wijzigt en zo zelf een trigger-event afvuurt).
        if (AutomationEngine::suppressed()) {
            return;
        }

        $resolve = $trigger['resolve'] ?? null;
        if (! is_callable($resolve)) {
            return;
        }

        $subject = $resolve($event);
        if (! $subject instanceof Order) {
            return;
        }

        $context = AutomationContext::forOrder($subject, $this->extraContext($event));
        $rules = AutomationEngine::rulesFor((string) $trigger['key'], (string) $subject->site_id);

        foreach ($rules as $rule) {
            if (ConditionEvaluator::matches($rule->conditions ?? [], $context)) {
                RunAutomationRuleJob::dispatch($rule, $subject)->onQueue('ecommerce');
            }
        }
    }

    /**
     * Trigger-specifieke extra waarden (bv. old_status/new_status bij
     * order.fulfillment_changed) rechtstreeks uit de publieke properties
     * van het event gehaald — dynamisch, zodat een trigger met eigen extra
     * velden hier niets hoeft toe te voegen. Model-properties (het
     * onderwerp zelf, bv. `order`/`orderReturn`) tellen niet mee: die
     * zitten al in de context via AutomationContext::forOrder().
     *
     * @return array<string, mixed>
     */
    private function extraContext(object $event): array
    {
        $extra = [];

        foreach (get_object_vars($event) as $property => $value) {
            if ($value instanceof Model) {
                continue;
            }

            $extra[Str::snake($property)] = $value;
        }

        return $extra;
    }
}
