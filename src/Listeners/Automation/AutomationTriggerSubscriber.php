<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Listeners\Automation;

use Illuminate\Support\Str;
use Illuminate\Events\Dispatcher;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Jobs\RunAutomationRuleJob;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationEngine;
use Dashed\DashedEcommerceCore\Support\Automation\AutomationContext;
use Dashed\DashedEcommerceCore\Support\Automation\ConditionEvaluator;

/**
 * Luistert bij boot dynamisch op alle event-classes die
 * MobileApiRegistry::automationTriggers() registreert — niet hardcoded, dus
 * een nieuwe trigger in OrderAutomationTriggers hoeft hier niets bij te
 * schrijven. Per event: onderwerp resolven via de trigger's `resolve`
 * (elk Model, niet alleen Order — zie handle()), waardecontext bouwen via
 * AutomationContext::for(), actieve regels voor deze trigger+site ophalen
 * via AutomationEngine::rulesFor() en per match (ConditionEvaluator::matches())
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
            // Sommige triggers (bv. tijd-triggers) vuren niet via events maar
            // via een uurlijkse scan — die hebben geen 'event'-key.
            if (! isset($trigger['event'])) {
                continue;
            }

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

        // Generiek onderwerp: een `resolve` mag elk Model teruggeven, niet
        // alleen een Order. Iets anders dan een Model (incl. null) betekent
        // dat deze trigger voor dit event wordt overgeslagen — fail-closed,
        // net als AutomationContext::for()'s default-tak.
        $subject = $resolve($event);
        if (! $subject instanceof Model) {
            return;
        }

        // Eén duidelijke plek voor de contextopbouw. Een klant-trigger
        // (order-onderwerp, maar klant-conditievelden) draagt de marker
        // `context => 'customer'` — in dat geval levert forCustomer($subject)
        // de $extra i.p.v. de gewone event-properties, zodat order_count/
        // total_spend/email/is_registered in de conditie-context terechtkomen.
        $extra = ($trigger['context'] ?? null) === 'customer'
            ? AutomationContext::forCustomer($subject)
            : $this->extraContext($event);
        $context = AutomationContext::for($subject, $extra);
        $rules = AutomationEngine::rulesFor((string) $trigger['key'], $this->siteIdFor($subject));

        foreach ($rules as $rule) {
            if (ConditionEvaluator::matches($rule->conditions ?? [], $context)) {
                RunAutomationRuleJob::dispatch($rule, $subject)->onQueue('ecommerce');
            }
        }
    }

    /**
     * Site-ID voor AutomationEngine::rulesFor(). Een Order heeft een scalar
     * `site_id`; een Product heeft (nog) geen `site_id` maar een
     * `site_ids`-array (een product kan bij meerdere sites horen). In B3
     * komt een Product in de praktijk niet via déze subscriber binnen:
     * voorraad-triggers zijn scan-gebaseerd (geen event, zie
     * TimeAutomationTriggers/RunTimeBasedAutomationRules-achtige opzet),
     * dus alleen order-onderwerpen (order-events, en straks klant-triggers —
     * ook order-onderwerp) komen hier binnen. Deze helper houdt de
     * site-afleiding alvast generiek voor een toekomstig, wél
     * event-gedreven, niet-order onderwerp: bij ontbrekende `site_id` valt
     * hij terug op de eerste waarde van `site_ids`, en anders op de actieve
     * site.
     */
    private function siteIdFor(Model $subject): string
    {
        if (filled($subject->site_id ?? null)) {
            return (string) $subject->site_id;
        }

        $siteIds = $subject->site_ids ?? [];
        if (is_array($siteIds) && $siteIds !== []) {
            return (string) $siteIds[0];
        }

        return (string) Sites::getActive();
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
