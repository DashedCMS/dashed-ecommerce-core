<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;

/**
 * Klant-triggers (B3 task 4): `customer.new` en `customer.nth_order` vuren
 * allebei op `OrderCreatedEvent`, met de Order als onderwerp (ook voor
 * gasten, die geen User hebben). Er is bewust geen apart drempel-veld op de
 * trigger: beide triggers matchen via de conditie op het `order_count`-veld
 * dat de klant-context aanlevert (`order_count eq 1` voor "nieuwe klant",
 * `order_count eq N` voor "N-de bestelling") — de gebruiker zet die conditie
 * zelf bij het opslaan van de regel.
 *
 * De `context => 'customer'`-marker is wat dit werkend maakt: zonder die
 * marker zou AutomationContext::for() voor een Order-onderwerp gewoon de
 * order-velden (total/status/...) opbouwen via forOrder(), niet de
 * klant-velden (order_count/total_spend/...) via forCustomer(). De koppeling
 * zit in AutomationTriggerSubscriber::handle(): die leest deze marker en
 * geeft dan `AutomationContext::forCustomer($subject)` mee als `$extra`,
 * i.p.v. de gewone `extraContext($event)`.
 */
class CustomerAutomationTriggers
{
    public static function register(MobileApiRegistry $registry): void
    {
        if (! method_exists($registry, 'registerAutomationTriggers')) {
            return;
        }

        $registry->registerAutomationTriggers([
            [
                'key' => 'customer.new',
                'label' => 'Nieuwe klant',
                'subject' => 'customer',
                'context' => 'customer',
                'event' => OrderCreatedEvent::class,
                'fields' => self::customerConditionFields(),
                'resolve' => fn (OrderCreatedEvent $event): Order => $event->order,
            ],
            [
                'key' => 'customer.nth_order',
                'label' => 'N-de bestelling van een klant',
                'subject' => 'customer',
                'context' => 'customer',
                'event' => OrderCreatedEvent::class,
                'fields' => self::customerConditionFields(),
                'resolve' => fn (OrderCreatedEvent $event): Order => $event->order,
            ],
        ]);
    }

    /**
     * De voorwaarde-velden die AutomationContext::forCustomer() aanlevert.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function customerConditionFields(): array
    {
        return [
            ['name' => 'order_count', 'label' => 'Aantal bestellingen', 'type' => 'number'],
            ['name' => 'total_spend', 'label' => 'Totaal besteed', 'type' => 'number'],
            ['name' => 'email', 'label' => 'E-mailadres', 'type' => 'text'],
            ['name' => 'is_registered', 'label' => 'Geregistreerde klant', 'type' => 'boolean'],
        ];
    }
}
