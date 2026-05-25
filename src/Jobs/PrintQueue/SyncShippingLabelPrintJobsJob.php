<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Jobs\PrintQueue;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        if (! (bool) Customsetting::get('print_queue.auto_print_label_on_generated', null, false)) {
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

        $sourceModel::query()
            ->whereNotNull('label_pdf_path')
            ->where('label_printed', false)
            ->whereNotIn('id', $existingPrintableIds)
            ->chunkById(100, function ($shippingOrders) use ($sourceModel): void {
                foreach ($shippingOrders as $shippingOrder) {
                    PrintJob::create([
                        'type' => PrintJobType::ShippingLabel,
                        'order_id' => $shippingOrder->order_id,
                        'printable_type' => $sourceModel,
                        'printable_id' => $shippingOrder->id,
                        'pdf_disk' => 'public',
                        'pdf_path' => $shippingOrder->label_pdf_path,
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
