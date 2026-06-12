<?php

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;
use Dashed\DashedEcommerceCore\EcommerceManager;
use Dashed\DashedEcommerceCore\Jobs\SyncOrderLabelStatusesJob;
use Dashed\DashedEcommerceCore\Contracts\ShippingLabelProvider;

/**
 * Minimale fake-carrier die het contract implementeert, zodat we de
 * provider-agnostische job kunnen testen zonder echte koppeling.
 */
class FakeLabelStatusProvider implements ShippingLabelProvider
{
    public ?int $receivedOrderId = null;

    public function __construct(
        private string $providerKey,
        private int $count = 0,
        private bool $throws = false,
    ) {
    }

    public function key(): string
    {
        return $this->providerKey;
    }

    public function label(): string
    {
        return ucfirst($this->providerKey);
    }

    public function failedOrders(): array
    {
        return [];
    }

    public function retry(int $id): void
    {
    }

    public function hasLabelsForOrder(Order $order): bool
    {
        return $this->count > 0;
    }

    public function syncOrderStatuses(Order $order): int
    {
        if ($this->throws) {
            throw new \RuntimeException('carrier down');
        }

        $this->receivedOrderId = $order->id;

        return $this->count;
    }
}

function setShippingLabelProviders(array $providers): void
{
    $ref = new ReflectionProperty(EcommerceManager::class, 'shippingLabelProviders');
    $ref->setAccessible(true);
    $ref->setValue(null, $providers);
}

function getShippingLabelProviders(): array
{
    $ref = new ReflectionProperty(EcommerceManager::class, 'shippingLabelProviders');
    $ref->setAccessible(true);

    return $ref->getValue();
}

beforeEach(function () {
    $this->originalProviders = getShippingLabelProviders();
});

afterEach(function () {
    setShippingLabelProviders($this->originalProviders);
});

it('roept elke geregistreerde provider aan voor de order en logt het resultaat', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-LBL-1']);

    $a = new FakeLabelStatusProvider('fake_a', 2);
    $b = new FakeLabelStatusProvider('fake_b', 3);
    setShippingLabelProviders(['fake_a' => $a, 'fake_b' => $b]);

    SyncOrderLabelStatusesJob::dispatchSync($order);

    expect($a->receivedOrderId)->toBe($order->id)
        ->and($b->receivedOrderId)->toBe($order->id);

    $log = OrderLog::where('order_id', $order->id)
        ->where('tag', 'order.labelstatus.synced')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->note)->toContain('5');
});

it('logt nog steeds wanneer een provider een fout gooit', function () {
    $order = Order::create(['email' => 'klant@example.com', 'status' => 'paid', 'invoice_id' => 'INV-LBL-2']);

    $ok = new FakeLabelStatusProvider('ok', 1);
    $bad = new FakeLabelStatusProvider('bad', 0, throws: true);
    setShippingLabelProviders(['bad' => $bad, 'ok' => $ok]);

    SyncOrderLabelStatusesJob::dispatchSync($order);

    expect($ok->receivedOrderId)->toBe($order->id)
        ->and(
            OrderLog::where('order_id', $order->id)
                ->where('tag', 'order.labelstatus.synced')
                ->exists()
        )->toBeTrue();
});
