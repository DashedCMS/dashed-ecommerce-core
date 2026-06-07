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
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;

class CreatePackingSlipPrintJobListener implements ShouldQueue
{
    public string $queue = 'ecommerce';

    public int $tries = 3;

    public function handle(OrderCreatedEvent $event): void
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

        PrintJob::create([
            'type' => PrintJobType::PackingSlip,
            'order_id' => $event->order->id,
            'status' => PrintJobStatus::Pending,
        ]);
    }
}
