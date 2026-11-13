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

    /** @param  int|null  $userId  wie de knop indrukte (de job zelf draait zonder login) */
    public function __construct(public Order $order, public ?int $userId = null)
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

        // ?? null: een job die vóór deze versie in de wachtrij kwam, kent
        // de eigenschap nog niet.
        $userId = $this->userId ?? null;

        OrderLog::createLog(
            orderId: $this->order->id,
            tag: 'order.labelstatus.synced',
            note: "{$updated} labelstatussen bijgewerkt",
            isSystem: $userId ? null : true,
            userId: $userId,
        );
    }
}
