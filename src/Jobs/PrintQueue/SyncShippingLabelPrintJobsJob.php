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
        $existingPrintableIds = PrintJob::query()
            ->where('printable_type', $sourceModel)
            ->pluck('printable_id')
            ->all();

        // Een label is "klaar om te printen" zodra het bij de vervoerder bestaat:
        // MyParcel heeft dan een shipment_id (de PDF wordt on-demand gedownload),
        // Veloyd een label_url. De PDF zelf lost PrintQueueController::pdf() op,
        // daarom zetten we hier bewust geen pdf_disk/pdf_path.
        $availableColumn = str_contains($sourceModel, 'MyParcel') ? 'shipment_id' : 'label_url';

        $sourceModel::query()
            ->whereNotNull($availableColumn)
            ->where('label_printed', false)
            ->whereNotIn('id', $existingPrintableIds)
            ->chunkById(100, function ($shippingOrders) use ($sourceModel): void {
                foreach ($shippingOrders as $shippingOrder) {
                    PrintJob::create([
                        'type' => PrintJobType::ShippingLabel,
                        'order_id' => $shippingOrder->order_id,
                        'printable_type' => $sourceModel,
                        'printable_id' => $shippingOrder->id,
                        'status' => PrintJobStatus::Pending,
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
