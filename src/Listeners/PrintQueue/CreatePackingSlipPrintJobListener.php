<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Listeners\PrintQueue;

use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;

class CreatePackingSlipPrintJobListener implements ShouldQueue
{
    public string $queue = 'ecommerce';

    public int $tries = 3;

    /**
     * Maakt automatisch een pakbon-print-job zodra een bestelling betaald is.
     *
     * Bewust gekoppeld aan OrderMarkedAsPaidEvent (niet OrderCreatedEvent): zo
     * worden er geen pakbonnen geprint voor onbetaalde/afgehaakte bestellingen.
     * Het paid-event kan voor één order meerdere keren afgaan (paid /
     * partially_paid / waiting_for_confirmation), daarom de dedup-guard.
     */
    public function handle(OrderMarkedAsPaidEvent $event): void
    {
        if (! (bool) Customsetting::get('print_queue.auto_print_on_new_order', null, null)) {
            return;
        }

        $hasPrinter = Printer::active()
            ->whereIn('type', [PrinterType::PackingSlip->value, PrinterType::Both->value])
            ->exists();

        if (! $hasPrinter) {
            return;
        }

        // Niet nog een pakbon-job aanmaken als er al één voor deze order loopt of
        // klaar is. Een eerder mislukte/geannuleerde job mag wél opnieuw.
        $alreadyQueued = PrintJob::query()
            ->where('order_id', $event->order->id)
            ->where('type', PrintJobType::PackingSlip->value)
            ->whereNotIn('status', [PrintJobStatus::Failed->value, PrintJobStatus::Cancelled->value])
            ->exists();

        if ($alreadyQueued) {
            return;
        }

        PrintJob::create([
            'type' => PrintJobType::PackingSlip,
            'order_id' => $event->order->id,
            'status' => PrintJobStatus::Pending,
        ]);
    }
}
