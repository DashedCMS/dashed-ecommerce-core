<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedMobileApi\MobileApiRegistry;

/**
 * Voorraad-triggers (B3 task 6): `stock.low`/`stock.back` hebben, net als de
 * tijd-triggers (B2), geen event — er is geen "moment" waarop de voorraad
 * verandert dat de AutomationTriggerSubscriber kan afvangen (een stock-update
 * gebeurt vanuit heel veel plekken: order-afronding, handmatige correctie,
 * import, ...). In plaats daarvan levert RunTimeBasedAutomationRules (dezelfde
 * uurlijkse scan als de tijd-regels) kandidaten via StockRuleScanner
 * (Task 5), en is de rol van deze klasse puur registratie: `type => 'stock'`
 * + `subject => 'product'` sturen het scan-commando (herkent een stock-regel
 * aan de hand van `type`, zie RunTimeBasedAutomationRules::scanStockRules())
 * en het Filament-condition-formulier. Bewust GEEN `event`/`resolve` — die
 * zijn er alleen voor event-gedreven triggers (zie AutomationTriggerSubscriber
 * die op die twee sleutels matcht).
 */
class StockAutomationTriggers
{
    public static function register(MobileApiRegistry $registry): void
    {
        if (! method_exists($registry, 'registerAutomationTriggers')) {
            return;
        }

        $registry->registerAutomationTriggers([
            [
                'key' => 'stock.low',
                'label' => 'Voorraad laag',
                'type' => 'stock',
                'subject' => 'product',
                'fields' => self::productConditionFields(),
            ],
            [
                'key' => 'stock.back',
                'label' => 'Voorraad hersteld',
                'type' => 'stock',
                'subject' => 'product',
                'fields' => self::productConditionFields(),
            ],
        ]);
    }

    /**
     * De voorwaarde-velden die AutomationContext::forProduct() aanlevert.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function productConditionFields(): array
    {
        return [
            ['name' => 'stock', 'label' => 'Voorraad', 'type' => 'number'],
            ['name' => 'price', 'label' => 'Prijs', 'type' => 'number'],
            ['name' => 'name', 'label' => 'Productnaam', 'type' => 'text'],
            ['name' => 'sku', 'label' => 'SKU', 'type' => 'text'],
        ];
    }
}
