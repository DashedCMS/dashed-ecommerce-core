<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderLog;

/**
 * Werkt de labelstatussen van één order bij over alle geregistreerde
 * verzendkoppelingen heen. Provider-agnostisch: elke ShippingLabelProvider
 * doet automatisch mee. Error-veilig: faalt per provider stil.
 */
class SyncOrderLabelStatusesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order)
    {
        $this->onQueue('ecommerce');
    }

    public function handle(): void
    {
        $updated = 0;

        foreach (ecommerce()->shippingLabelProviders() as $provider) {
            try {
                $updated += $provider->syncOrderStatuses($this->order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        OrderLog::create([
            'order_id' => $this->order->id,
            'user_id' => null,
            'tag' => 'order.labelstatus.synced',
            'note' => "{$updated} labelstatussen bijgewerkt",
        ]);
    }
}
