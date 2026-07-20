<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Support;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Classes\Orders;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnApprovedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderReturnRequestedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;

/**
 * Registreert de triggers voor automatiseringsregels ("als dit gebeurt en
 * deze voorwaarden gelden, doe dat") die uitgaan van een Order. Het onderwerp
 * (subject) is in fase 1 altijd de Order — ook bij de twee retour-triggers,
 * zodat de voorwaarden en de contextopbouw op één plek blijven. Retour-
 * specifieke velden (reden, aantal regels) zijn geen onderdeel van fase 1 en
 * kunnen later additief worden toegevoegd.
 */
class OrderAutomationTriggers
{
    public static function register(MobileApiRegistry $registry): void
    {
        if (! method_exists($registry, 'registerAutomationTriggers')) {
            return;
        }

        $registry->registerAutomationTriggers([
            [
                'key' => 'order.created',
                'label' => 'Bestelling aangemaakt',
                'subject' => 'order',
                'event' => OrderCreatedEvent::class,
                'fields' => self::orderConditionFields(),
                'resolve' => fn (OrderCreatedEvent $event): Order => $event->order,
            ],
            [
                'key' => 'order.paid',
                'label' => 'Bestelling betaald',
                'subject' => 'order',
                'event' => OrderMarkedAsPaidEvent::class,
                'fields' => self::orderConditionFields(),
                'resolve' => fn (OrderMarkedAsPaidEvent $event): Order => $event->order,
            ],
            [
                'key' => 'order.cancelled',
                'label' => 'Bestelling geannuleerd',
                'subject' => 'order',
                'event' => OrderCancelledEvent::class,
                'fields' => self::orderConditionFields(),
                'resolve' => fn (OrderCancelledEvent $event): Order => $event->order,
            ],
            [
                'key' => 'order.fulfillment_changed',
                'label' => 'Fulfillmentstatus gewijzigd',
                'subject' => 'order',
                'event' => OrderFulfillmentStatusChangedEvent::class,
                'fields' => [
                    ...self::orderConditionFields(),
                    ['name' => 'old_status', 'label' => 'Oude fulfillmentstatus', 'type' => 'select', 'options' => Orders::getFulfillmentStatusses()],
                    ['name' => 'new_status', 'label' => 'Nieuwe fulfillmentstatus', 'type' => 'select', 'options' => Orders::getFulfillmentStatusses()],
                ],
                'resolve' => fn (OrderFulfillmentStatusChangedEvent $event): Order => $event->order,
            ],
            [
                'key' => 'order.return_requested',
                'label' => 'Retour aangevraagd',
                'subject' => 'order',
                'event' => OrderReturnRequestedEvent::class,
                'fields' => self::orderConditionFields(),
                'resolve' => fn (OrderReturnRequestedEvent $event): Order => $event->orderReturn->order,
            ],
            [
                'key' => 'order.return_approved',
                'label' => 'Retour goedgekeurd',
                'subject' => 'order',
                'event' => OrderReturnApprovedEvent::class,
                'fields' => self::orderConditionFields(),
                'resolve' => fn (OrderReturnApprovedEvent $event): Order => $event->orderReturn->order,
            ],
        ]);
    }

    /**
     * De voorwaarde-velden die voor elke order-trigger beschikbaar zijn.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function orderConditionFields(): array
    {
        return [
            ['name' => 'total', 'label' => 'Totaalbedrag', 'type' => 'number'],
            ['name' => 'country', 'label' => 'Land', 'type' => 'select', 'options' => fn () => self::countryOptions()],
            ['name' => 'origin', 'label' => 'Herkomst', 'type' => 'select', 'options' => fn () => self::originOptions()],
            ['name' => 'payment_method', 'label' => 'Betaalmethode', 'type' => 'select', 'options' => fn () => self::paymentMethodOptions()],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::statusOptions()],
            ['name' => 'fulfillment_status', 'label' => 'Fulfillmentstatus', 'type' => 'select', 'options' => Orders::getFulfillmentStatusses()],
            ['name' => 'product_count', 'label' => 'Aantal producten', 'type' => 'number'],
            ['name' => 'has_discount_code', 'label' => 'Heeft kortingscode', 'type' => 'boolean'],
        ];
    }

    /** @return array<string, string> */
    private static function statusOptions(): array
    {
        return [
            'paid' => 'Betaald',
            'partially_paid' => 'Gedeeltelijk betaald',
            'waiting_for_confirmation' => 'Wachten op bevestiging',
            'pending' => 'Lopende aankoop',
            'concept' => 'Concept',
            'cancelled' => 'Geannuleerd',
            'return' => 'Retour',
        ];
    }

    /**
     * Alleen origins/landen/betaalmethodes van de actieve site — zonder deze
     * scope lekken deze condition-opties door naar andere sites in een
     * multi-site installatie (de admin van site A zou landen/origins/
     * betaalmethodes van site B in zijn voorwaarde-dropdown zien, en zou een
     * voorwaarde kunnen opslaan die op zijn eigen site nooit matcht). `Order`
     * heeft hiervoor al `scopeThisSite()`; `PaymentMethod` niet, dus die
     * scopen we hier expliciet op dezelfde manier als
     * AutomationRuleController.
     *
     * @return array<string, string>
     */
    private static function originOptions(): array
    {
        $options = [];
        foreach (Order::query()->thisSite()->whereNotNull('order_origin')->distinct()->pluck('order_origin') as $origin) {
            $options[$origin] = ucfirst($origin);
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function countryOptions(): array
    {
        $options = [];
        foreach (Order::query()->thisSite()->whereNotNull('country')->where('country', '!=', '')->distinct()->orderBy('country')->pluck('country') as $country) {
            $options[$country] = $country;
        }

        return $options;
    }

    /** @return array<string, string> */
    private static function paymentMethodOptions(): array
    {
        return PaymentMethod::query()
            ->where('site_id', (string) Sites::getActive())
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }
}
