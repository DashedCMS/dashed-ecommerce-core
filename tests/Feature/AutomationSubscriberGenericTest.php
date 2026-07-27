<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedEcommerceCore\Models\AutomationRule;
use Dashed\DashedEcommerceCore\Jobs\RunAutomationRuleJob;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Listeners\Automation\AutomationTriggerSubscriber;

/**
 * Task 2 (B3): de subscriber moet niet-order onderwerpen aankunnen — een
 * `resolve` die een Product teruggeeft, gematcht via
 * AutomationContext::for() (Task 1) i.p.v. het hardcoded forOrder(). Deze
 * test registreert een tijdelijke test-trigger op een eigen event-class met
 * een Product als onderwerp, en verifieert daarnaast dat een bestaande
 * order-trigger identiek blijft werken na de generalisatie.
 *
 * BELANGRIJK — waarom hier handmatig `subscribe()` opnieuw wordt aangeroepen:
 * AutomationTriggerSubscriber::subscribe() luistert bij app-boot op alle
 * event-classes die op dát moment al in MobileApiRegistry::automationTriggers()
 * zitten (zie AutomationEngineTest voor het normale pad, waar dat volstaat).
 * Onze test-trigger bestaat pas ná die boot, dus zonder een nieuwe
 * subscribe()-aanroep zou de subscriber er nooit een listener voor
 * registreren. Dat resubscriben registreert ook een tweede listener voor de
 * al bestaande triggers (bv. order.created) — onschadelijk hier, want elke
 * Pest-test krijgt een verse Application-boot en de order-trigger-test
 * hieronder roept subscribe() zelf nooit aan.
 */
class SubscriberGenericTestProductEvent
{
    public function __construct(public Product $product)
    {
    }
}

function subscriberGenericTestSite(): string
{
    return Sites::getActive();
}

function makeSubscriberGenericProductGroup(): ProductGroup
{
    return ProductGroup::create([
        'name' => ['en' => 'Test Group ' . uniqid()],
        'slug' => ['en' => 'test-group-' . uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => [subscriberGenericTestSite()],
    ]);
}

function makeSubscriberGenericProduct(array $overrides = []): Product
{
    $group = makeSubscriberGenericProductGroup();

    // withoutEvents: bypast de saved-event-dispatch (UpdateProductInformationJob,
    // MySQL-specifieke GREATEST) — zelfde patroon als StockSyncTest/BackInStockServiceTest.
    return Product::withoutEvents(function () use ($group, $overrides) {
        return Product::create(array_merge([
            'name' => ['en' => 'Test product'],
            'slug' => ['en' => 'test-product-' . uniqid()],
            'site_ids' => [subscriberGenericTestSite()],
            'product_group_id' => $group->id,
            'use_stock' => true,
            'stock' => 3,
            'total_stock' => 3,
            'in_stock' => true,
            'stock_status' => 'in_stock',
            'price' => 10.00,
            'current_price' => 10.00,
        ], $overrides));
    });
}

it('dispatches a RunAutomationRuleJob for a non-order subject resolved via Product, matched on a stock condition', function () {
    Queue::fake();
    $site = subscriberGenericTestSite();

    app(MobileApiRegistry::class)->registerAutomationTriggers([
        [
            'key' => 'test.product_stock_changed',
            'label' => 'Test: productvoorraad gewijzigd',
            'subject' => 'product',
            'event' => SubscriberGenericTestProductEvent::class,
            'resolve' => fn (SubscriberGenericTestProductEvent $event): Product => $event->product,
        ],
    ]);

    (new AutomationTriggerSubscriber())->subscribe(app('events'));

    $rule = AutomationRule::create([
        'site_id' => $site,
        'name' => 'Lage voorraad',
        'trigger' => 'test.product_stock_changed',
        'conditions' => [['field' => 'stock', 'operator' => 'lt', 'value' => 5]],
        'actions' => [],
        'is_active' => true,
    ]);

    $product = makeSubscriberGenericProduct(['stock' => 2]);

    event(new SubscriberGenericTestProductEvent($product));

    Queue::assertPushed(RunAutomationRuleJob::class, 1);
    Queue::assertPushed(RunAutomationRuleJob::class, fn ($job) => $job->rule->is($rule) && $job->subject->is($product));
    Queue::assertPushedOn('ecommerce', RunAutomationRuleJob::class);
});

it('still dispatches a RunAutomationRuleJob for an existing order trigger after the subscriber generalization', function () {
    Queue::fake();
    $site = subscriberGenericTestSite();

    $rule = AutomationRule::create([
        'site_id' => $site,
        'name' => 'Nieuwe order',
        'trigger' => 'order.created',
        'conditions' => [],
        'actions' => [],
        'is_active' => true,
    ]);

    $order = Order::create([
        'email' => 'klant@example.com',
        'status' => 'concept',
    ]);

    event(new OrderCreatedEvent($order));

    Queue::assertPushed(RunAutomationRuleJob::class, 1);
    Queue::assertPushed(RunAutomationRuleJob::class, fn ($job) => $job->rule->is($rule) && $job->subject->is($order));
    Queue::assertPushedOn('ecommerce', RunAutomationRuleJob::class);
});
