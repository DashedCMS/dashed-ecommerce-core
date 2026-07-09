<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\Models\OrderReturn;
use Dashed\DashedMobileApi\Support\NotificationCenter;
use Dashed\DashedMobileApi\Support\PushNotification;
use Dashed\DashedEcommerceCore\Support\ReturnNotifier;

/** Spy die de opgebouwde push vastlegt zonder Expo te raken. */
class ReturnPushSpy extends PushNotification
{
    public array $captured = [];

    public function __construct()
    {
    }

    public function type(string $key): self
    {
        $this->captured['type'] = $key;

        return $this;
    }

    public function site(?string $siteId): self
    {
        return $this;
    }

    public function title(string $title): self
    {
        return $this;
    }

    public function body(string $body): self
    {
        return $this;
    }

    public function route(?string $route): self
    {
        $this->captured['route'] = $route;

        return $this;
    }

    public function data(array $data): self
    {
        return $this;
    }

    public function toAbility(string $ability): self
    {
        $this->captured['ability'] = $ability;

        return $this;
    }

    public function send(): void
    {
        $GLOBALS['__return_push_sent'][] = $this->captured;
    }
}

class FakeReturnCenter extends NotificationCenter
{
    public function __construct()
    {
    }

    public function push(): PushNotification
    {
        return new ReturnPushSpy();
    }
}

beforeEach(function () {
    $GLOBALS['__return_push_sent'] = [];
    app()->instance(NotificationCenter::class, new FakeReturnCenter());
});

it('fires return.requested with the deep-link route and orders.read ability', function () {
    $order = Order::create(['site_id' => 'site', 'email' => 'a@b.nl', 'invoice_id' => 'INV1', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'site_id' => 'site', 'email' => 'a@b.nl']);

    ReturnNotifier::requested($return->fresh());

    expect($GLOBALS['__return_push_sent'])->toHaveCount(1)
        ->and($GLOBALS['__return_push_sent'][0]['type'])->toBe('return.requested')
        ->and($GLOBALS['__return_push_sent'][0]['route'])->toBe("/return/{$return->id}")
        ->and($GLOBALS['__return_push_sent'][0]['ability'])->toBe('orders.read');
});

it('fires return.label_failed when an order.return-label-failed log is created', function () {
    $order = Order::create(['site_id' => 'site', 'email' => 'a@b.nl', 'invoice_id' => 'INV2', 'status' => 'paid']);
    $return = OrderReturn::create(['order_id' => $order->id, 'site_id' => 'site', 'email' => 'a@b.nl', 'status' => OrderReturn::STATUS_APPROVED]);

    $log = new OrderLog();
    $log->order_id = $order->id;
    $log->tag = 'order.return-label-failed';
    $log->save();

    expect($GLOBALS['__return_push_sent'])->toHaveCount(1)
        ->and($GLOBALS['__return_push_sent'][0]['type'])->toBe('return.label_failed')
        ->and($GLOBALS['__return_push_sent'][0]['route'])->toBe("/return/{$return->id}");
});

it('does not push for unrelated order logs', function () {
    $order = Order::create(['site_id' => 'site', 'email' => 'a@b.nl', 'invoice_id' => 'INV3', 'status' => 'paid']);

    $log = new OrderLog();
    $log->order_id = $order->id;
    $log->tag = 'order.some-other-event';
    $log->save();

    expect($GLOBALS['__return_push_sent'])->toHaveCount(0);
});
