<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands\PrintQueue;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

class BackfillPrintedShippingLabelsCommand extends Command
{
    protected $signature = 'dashed-ecommerce:backfill-printed-shipping-labels';

    protected $description = 'Zet met terugwerkende kracht label_printed=1 voor verzendlabels die al via de app/printer zijn geprint (er bestaat een ShippingLabel-print-job), zodat ze uit de "Download labels"-wachtrij verdwijnen.';

    public function handle(): int
    {
        // Een job dat (nog) niet geprint is, telt niet: Failed/Cancelled blijven
        // in de download-lijst staan zodat de admin ze opnieuw kan ophalen.
        $printedStatuses = [
            PrintJobStatus::Pending->value,
            PrintJobStatus::Claimed->value,
            PrintJobStatus::Printing->value,
            PrintJobStatus::Done->value,
        ];

        // Order-id's met een order-niveau verzendlabel-print-job (geen specifieke
        // label gekozen in de app) → al die order-labels gelden als geprint.
        $orderLevelOrderIds = PrintJob::query()
            ->where('type', PrintJobType::ShippingLabel->value)
            ->whereNull('printable_id')
            ->whereIn('status', $printedStatuses)
            ->pluck('order_id')
            ->unique()
            ->all();

        $total = 0;

        foreach (PrintJob::shippingLabelSources() as $sourceModel) {
            // Rijen die direct aan een print-job gekoppeld zijn (specifiek label).
            $linkedIds = PrintJob::query()
                ->where('type', PrintJobType::ShippingLabel->value)
                ->where('printable_type', $sourceModel)
                ->whereNotNull('printable_id')
                ->whereIn('status', $printedStatuses)
                ->pluck('printable_id')
                ->unique()
                ->all();

            $updated = $sourceModel::query()
                ->where('label_printed', 0)
                ->where(function ($query) use ($linkedIds, $orderLevelOrderIds): void {
                    $query
                        ->whereIn('id', $linkedIds)
                        ->orWhereIn('order_id', $orderLevelOrderIds);
                })
                ->update(['label_printed' => 1]);

            if ($updated > 0) {
                $this->info(class_basename($sourceModel) . ': ' . $updated . ' label(s) als geprint gemarkeerd.');
            }

            $total += $updated;
        }

        $this->info('Klaar. Totaal als geprint gemarkeerd: ' . $total . '.');

        return self::SUCCESS;
    }
}
