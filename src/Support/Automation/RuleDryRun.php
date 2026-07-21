<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support\Automation;

use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\Order;
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
 * draaien, dus is er niets zinvols te beschrijven.
 */
class RuleDryRun
{
    /**
     * @return array{
     *     matched: bool,
     *     context: array<string, mixed>,
     *     actions: array<int, array{key: string, label: string, params: array<string, mixed>}>,
     * }
     */
    public static function for(AutomationRule $rule, Order $order): array
    {
        $context = AutomationContext::forOrder($order);
        $matched = ConditionEvaluator::matches($rule->conditions ?? [], $context);

        return [
            'matched' => $matched,
            'context' => $context,
            'actions' => $matched ? self::describeActions($rule) : [],
        ];
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
        $registry = app(MobileApiRegistry::class);

        $described = [];
        foreach (($rule->actions ?? []) as $action) {
            $key = is_array($action) ? (string) ($action['key'] ?? '') : '';
            if ($key === '') {
                continue;
            }

            $definition = $registry->orderAction($key);
            $params = is_array($action) && is_array($action['params'] ?? null) ? $action['params'] : [];

            $described[] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'params' => $params,
            ];
        }

        return $described;
    }
}
