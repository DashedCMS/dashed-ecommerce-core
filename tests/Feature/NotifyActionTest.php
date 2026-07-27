<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedMobileApi\MobileApiRegistry;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedMobileApi\Support\PushNotification;
use Dashed\DashedEcommerceCore\Support\MobileOrderActions;
use Dashed\DashedMobileApi\Support\NotificationCenter;

/** Spy die de opgebouwde push vastlegt zonder Expo te raken. */
class NotifyPushSpy extends PushNotification
{
    public array $captured = [];

    public function __construct()
    {
    }

    public function title(string $title): self
    {
        $this->captured['title'] = $title;

        return $this;
    }

    public function body(string $body): self
    {
        $this->captured['body'] = $body;

        return $this;
    }

    public function route(?string $route): self
    {
        $this->captured['route'] = $route;

        return $this;
    }

    public function toAbility(string $ability): self
    {
        $this->captured['ability'] = $ability;

        return $this;
    }

    public function send(): void
    {
        $GLOBALS['__notify_push_sent'][] = $this->captured;
    }
}

class FakeNotifyCenter extends NotificationCenter
{
    public function __construct()
    {
    }

    public function push(): PushNotification
    {
        return new NotifyPushSpy();
    }
}

function makeNotifyProduct(array $attributes = []): Product
{
    $group = ProductGroup::create([
        'name' => ['en' => 'Groep '.uniqid()],
        'slug' => ['en' => 'groep-'.uniqid()],
        'short_description' => ['en' => ''],
        'description' => ['en' => ''],
        'content' => ['en' => ''],
        'search_terms' => ['en' => ''],
        'site_ids' => ['default'],
    ]);

    return Product::withoutEvents(fn () => Product::create(array_merge([
        'product_group_id' => $group->id,
        'name' => ['en' => 'Widget '.uniqid()],
        'slug' => ['en' => 'widget-'.uniqid()],
        'site_ids' => ['default'],
        'price' => 10.00,
        'current_price' => 10.00,
        'vat_rate' => 21,
        'use_stock' => true,
        'stock' => 0,
        'total_stock' => 0,
        'in_stock' => false,
    ], $attributes)));
}

beforeEach(function () {
    $GLOBALS['__notify_push_sent'] = [];
    app()->instance(NotificationCenter::class, new FakeNotifyCenter());
});

it('notify is automatable en staat in de catalogus', function () {
    $registry = app(MobileApiRegistry::class);
    MobileOrderActions::register($registry);

    $notify = collect($registry->orderActions())->firstWhere('key', 'notify');

    expect($notify)->not->toBeNull()
        ->and($notify['automatable'])->toBeTrue();
});

it('routeert naar /product/{id} met de productnaam als default body op een Product-onderwerp', function () {
    $registry = app(MobileApiRegistry::class);
    MobileOrderActions::register($registry);

    $product = makeNotifyProduct();
    $handle = $registry->orderAction('notify')['handle'];

    $handle($product, []);

    expect($GLOBALS['__notify_push_sent'])->toHaveCount(1)
        ->and($GLOBALS['__notify_push_sent'][0]['route'])->toBe("/product/{$product->id}")
        ->and($GLOBALS['__notify_push_sent'][0]['title'])->toBe('Voorraad-melding')
        ->and($GLOBALS['__notify_push_sent'][0]['body'])->toBe($product->name);
});

it('routeert naar /order/{id} met het e-mailadres als default body op een Order-onderwerp', function () {
    $registry = app(MobileApiRegistry::class);
    MobileOrderActions::register($registry);

    $order = Order::create(['site_id' => 'site', 'email' => 'klant@voorbeeld.nl', 'invoice_id' => 'INV-NOTIFY-1', 'status' => 'paid']);
    $handle = $registry->orderAction('notify')['handle'];

    $handle($order, []);

    expect($GLOBALS['__notify_push_sent'])->toHaveCount(1)
        ->and($GLOBALS['__notify_push_sent'][0]['route'])->toBe("/order/{$order->id}")
        ->and($GLOBALS['__notify_push_sent'][0]['title'])->toBe('Klant-melding')
        ->and($GLOBALS['__notify_push_sent'][0]['body'])->toBe('klant@voorbeeld.nl');
});

it('laat een ingevulde title winnen van de default', function () {
    $registry = app(MobileApiRegistry::class);
    MobileOrderActions::register($registry);

    $product = makeNotifyProduct();
    $handle = $registry->orderAction('notify')['handle'];

    $handle($product, ['title' => 'Weer op voorraad!']);

    expect($GLOBALS['__notify_push_sent'])->toHaveCount(1)
        ->and($GLOBALS['__notify_push_sent'][0]['title'])->toBe('Weer op voorraad!');
});

it('stuurt geen push voor een onbekend onderwerp', function () {
    $registry = app(MobileApiRegistry::class);
    MobileOrderActions::register($registry);

    $handle = $registry->orderAction('notify')['handle'];

    $handle(new \Dashed\DashedCore\Models\User(), []);

    expect($GLOBALS['__notify_push_sent'])->toHaveCount(0);
});
