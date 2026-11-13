<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Jobs\PrintQueue;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

class SyncShippingLabelPrintJobsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('ecommerce');
    }

    /**
     * Snelle controle voor de scheduler: met de instelling uit komt er geen
     * job in de wachtrij. Lukt de controle niet, dan gaat de job gewoon.
     */
    public static function hasWork(): bool
    {
        return rescue(
            fn () => (bool) Customsetting::get('print_queue.auto_print_label_on_generated', null, null),
            true,
            false,
        );
    }

    public function handle(): void
    {
        if (! (bool) Customsetting::get('print_queue.auto_print_label_on_generated', null, null)) {
            return;
        }

        foreach ($this->labelSources() as $sourceModel) {
            $this->syncSource($sourceModel);
        }
    }

    private function syncSource(string $sourceModel): void
    {
        $sourceTable = (new $sourceModel())->getTable();

        // "Klaar om te printen" verschilt per vervoerder: MyParcel heeft een
        // shipment_id (PDF wordt on-demand gedownload), Veloyd heeft het PDF al op
        // de public disk (label_pdf_path; label_url wordt niet gebruikt). De
        // label_printed-vlag deugt niet als filter (Veloyd zet 'm direct op 1),
        // dus de dedup op printable_id voorkomt dubbele jobs en het tijdvenster
        // voorkomt dat de eerste run het hele verleden in één keer print.
        // Beide vervoerders hebben een shipment_id zodra de zending bestaat; de
        // PDF wordt bij het printen on-demand opgehaald.
        $sourceModel::query()
            ->whereNotNull('shipment_id')
            ->where('created_at', '>=', now()->subDays(2))
            // Per rij in het venster, dus zonder alle oude printable_ids te laden.
            ->whereNotExists(
                PrintJob::query()
                    ->where('printable_type', $sourceModel)
                    ->whereColumn('printable_id', "{$sourceTable}.id")
                    ->toBase()
            )
            ->chunkById(100, function ($shippingOrders) use ($sourceModel): void {
                foreach ($shippingOrders as $shippingOrder) {
                    PrintJob::create([
                        'type' => PrintJobType::ShippingLabel,
                        'order_id' => $shippingOrder->order_id,
                        'printable_type' => $sourceModel,
                        'printable_id' => $shippingOrder->id,
                        'status' => PrintJobStatus::Pending,
                        'channel' => PrintJob::CHANNEL_AUTOMATIC,
                    ]);
                }
            });
    }

    /** @return array<int, class-string> */
    private function labelSources(): array
    {
        return array_values(array_filter([
            class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)
                ? \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class
                : null,
            class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)
                ? \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class
                : null,
        ]));
    }
}
