<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\AutomationRule;

/**
 * Droogloop: test één automatiseringsregel tegen één echte bestelling zonder
 * ook maar iets uit te voeren. Hergebruikt exact dezelfde bouwstenen als de
 * echte uitvoering — ConditionEvaluator::matches() en
 * AutomationContext::forOrder() — zodat "zou deze regel matchen" hier altijd
 * hetzelfde antwoord geeft als AutomationEngine op de echte trigger zou
 * geven. Het enige verschil: acties worden enkel BESCHREVEN (key/label/params
 * uit de registry + de opgeslagen regel-params), nooit hun `handle`
 * aangeroepen — dit is een test-tegen-bestelling-scherm, geen uitvoerpad.
 *
 * Geeft geen acties terug wanneer de regel niet matcht: er zou dan toch niets
 * draaien, dus is er niets zinvols te beschrijven. Uitzondering: wanneer
 * `undeterminable_fields` niet leeg is (zie hieronder) wordt er tóch
 * beschreven, omdat "matcht niet" daar geen eerlijk antwoord is.
 *
 * `AutomationContext::forOrder()` wordt hier ALTIJD zonder `$extra`
 * aangeroepen. Een droogloop test tegen een bestaande, statische order en kan
 * de delta-velden die AutomationTriggerSubscriber::extraContext() per event
 * toevoegt (bv. old_status/new_status bij order.fulfillment_changed) niet
 * kennen — die bestaan pas op het moment van de échte gebeurtenis. Zelf een
 * waarde verzinnen zou een stille misser kunnen zijn: de droogloop zou dan
 * een matchresultaat tonen dat de motor op het echte event nooit zo zou
 * geven. In plaats daarvan houdt `undeterminable_fields` bij op welke van dié
 * event-only velden déze regel filtert, zodat de UI een waarschuwing kan
 * tonen in plaats van een misleidend definitief "matched: false".
 */
class RuleDryRun
{
    /**
     * @return array{
     *     matched: bool,
     *     undeterminable_fields: array<int, string>,
     *     context: array<string, mixed>,
     *     actions: array<int, array{key: string, label: string, params: array<string, mixed>}>,
     * }
     */
    public static function for(AutomationRule $rule, Order $order): array
    {
        $context = AutomationContext::forOrder($order);
        $matched = ConditionEvaluator::matches($rule->conditions ?? [], $context);
        $undeterminableFields = self::undeterminableFields($rule, $context);

        // Een 'matched === false' die uitsluitend komt doordat een event-only
        // veld (per definitie afwezig in $context) ConditionEvaluator's
        // fail-safe raakt, is geen eerlijke "zou niet draaien" — de acties
        // worden dan alsnog beschreven, ter info, net als bij een echte match.
        $describeActions = $matched || $undeterminableFields !== [];

        return [
            'matched' => $matched,
            'undeterminable_fields' => $undeterminableFields,
            'context' => $context,
            'actions' => $describeActions ? self::describeActions($rule) : [],
        ];
    }

    /**
     * Welke conditie-velden van déze regel filteren op een veld dat alleen
     * tijdens de échte gebeurtenis bestaat — d.w.z. het zit niet in $context
     * (die nooit `$extra` krijgt), terwijl de trigger het wél als geldig
     * conditieveld registreert?
     *
     * Bron van waarheid: de trigger's geregistreerde `fields`
     * (OrderAutomationTriggers::register()), niet reflectie op de event-class.
     * Die twee lopen per constructie nooit uit de pas: een veld dat niet in
     * `fields` staat is in AutomationRuleResource's conditie-Select sowieso
     * niet te kiezen, dus kan hier ook nooit gemarkeerd hoeven worden. Voor
     * `order.fulfillment_changed` bevat `fields` naast de kernvelden ook
     * `old_status`/`new_status` — precies de twee velden die
     * AutomationTriggerSubscriber::extraContext() dynamisch uit
     * OrderFulfillmentStatusChangedEvent's publieke properties haalt.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, string>
     */
    private static function undeterminableFields(AutomationRule $rule, array $context): array
    {
        $triggerKey = $rule->trigger;
        if (! is_string($triggerKey) || $triggerKey === '') {
            return [];
        }

        $registry = self::registry();
        if ($registry === null) {
            return [];
        }

        $trigger = $registry->automationTrigger($triggerKey);
        if ($trigger === null) {
            return [];
        }

        $triggerFields = is_array($trigger['fields'] ?? null) ? $trigger['fields'] : [];
        $triggerFieldNames = [];
        foreach ($triggerFields as $field) {
            $name = is_array($field) ? ($field['name'] ?? null) : null;
            if (is_string($name) && $name !== '') {
                $triggerFieldNames[] = $name;
            }
        }

        // Velden die de trigger kent maar die niet in de (event-loze) context
        // zitten zijn "event-only" voor déze trigger.
        $eventOnlyFields = array_diff($triggerFieldNames, array_keys($context));
        if ($eventOnlyFields === []) {
            return [];
        }

        // ... en dan alleen degene waarop déze regel daadwerkelijk filtert.
        $conditionFields = [];
        foreach (($rule->conditions ?? []) as $condition) {
            $field = is_array($condition) ? ($condition['field'] ?? null) : null;
            if (is_string($field) && in_array($field, $eventOnlyFields, true) && ! in_array($field, $conditionFields, true)) {
                $conditionFields[] = $field;
            }
        }

        return $conditionFields;
    }

    /**
     * Beschrijft de opgeslagen acties van de regel via de registry-definitie
     * (voor het label) en de op de regel opgeslagen params — nooit via
     * `handle`. Een onbekende actie-key (bv. een sindsdien verwijderde actie)
     * crasht niet: die valt terug op de kale key als label, net als
     * AutomationRuleResource::actionItemLabel() dat al doet voor het
     * regel-formulier.
     *
     * @return array<int, array{key: string, label: string, params: array<string, mixed>}>
     */
    private static function describeActions(AutomationRule $rule): array
    {
        $registry = self::registry();

        $described = [];
        foreach (($rule->actions ?? []) as $action) {
            $key = is_array($action) ? (string) ($action['key'] ?? '') : '';
            if ($key === '') {
                continue;
            }

            // Zonder mobile-api geen registry-definitie: val terug op de kale
            // key als label, net als bij een onbekende actie-key.
            $definition = $registry?->orderAction($key);
            $params = is_array($action) && is_array($action['params'] ?? null) ? $action['params'] : [];

            $described[] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'params' => $params,
            ];
        }

        return $described;
    }

    /**
     * De MobileApiRegistry-singleton, of null wanneer dashed-mobile-api niet
     * geïnstalleerd is. ec-core vereist dat package niet (soft dependency),
     * dus mag de droogloop niet klappen op een ontbrekende registry — zelfde
     * class_exists-guard als AutomationRuleResource::registry().
     */
    private static function registry(): ?MobileApiRegistry
    {
        if (! class_exists(MobileApiRegistry::class)) {
            return null;
        }

        return app(MobileApiRegistry::class);
    }
}
