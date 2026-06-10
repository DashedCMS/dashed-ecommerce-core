<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Commands\PrintQueue;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

/**
 * Test de hele print-keten (CMS → daemon → CUPS → printer) door voor een echte
 * order zowel een pakbon- als een verzendlabel-job in de wachtrij te zetten.
 * Pakbon gaat (op type) naar de pakbon/both-printer, label naar de label/both-printer.
 */
class TestPrintBothCommand extends Command
{
    protected $signature = 'print-queue:test-both {order? : Order-ID (standaard de meest recente order)}';

    protected $description = 'Zet een pakbon- én verzendlabel-printjob in de wachtrij om beide printers te testen.';

    public function handle(): int
    {
        $order = $this->argument('order')
            ? Order::find((int) $this->argument('order'))
            : Order::query()->latest('id')->first();

        if (! $order) {
            $this->error('Geen order gevonden. Geef een order-ID mee: php artisan print-queue:test-both <id>');

            return self::FAILURE;
        }

        $this->info("Testorder: #{$order->id}" . ($order->invoice_id ? " (factuur {$order->invoice_id})" : ''));

        // Laat per type zien welke actieve printers de job zullen oppakken.
        $packingPrinters = Printer::active()
            ->whereIn('type', [PrinterType::PackingSlip->value, PrinterType::Both->value])
            ->pluck('cups_name')->all();
        $labelPrinters = Printer::active()
            ->whereIn('type', [PrinterType::ShippingLabel->value, PrinterType::Both->value])
            ->pluck('cups_name')->all();

        $this->line('  Pakbon → ' . ($packingPrinters ? implode(', ', $packingPrinters) : 'GEEN actieve printer (job blijft hangen!)'));
        $this->line('  Label  → ' . ($labelPrinters ? implode(', ', $labelPrinters) : 'GEEN actieve printer (job blijft hangen!)'));

        // 1. Pakbon — echte PDF, wordt on-demand gegenereerd bij ophalen.
        $packing = PrintJob::create([
            'type' => PrintJobType::PackingSlip,
            'order_id' => $order->id,
            'status' => PrintJobStatus::Pending,
        ]);
        $this->info("  ✔ Pakbon-job {$packing->ulid}");

        // 2. Verzendlabel — echt MyParcel-label indien aanwezig, anders de
        //    ingebouwde test-pagina zodat de DYMO toch fysiek test.
        $labelAttrs = [
            'type' => PrintJobType::ShippingLabel,
            'order_id' => $order->id,
            'status' => PrintJobStatus::Pending,
        ];

        // Een label is beschikbaar zodra welke vervoerder dan ook er één heeft:
        // MyParcel (shipment_id) of Veloyd (label_url). De PDF wordt on-demand
        // opgehaald door PrintQueueController::pdf().
        $hasMyParcel = class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)
            && \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::where('order_id', $order->id)
                ->whereNotNull('shipment_id')
                ->exists();
        $hasVeloyd = class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)
            && \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::where('order_id', $order->id)
                ->whereNotNull('shipment_id')
                ->exists();
        $hasLabel = $hasMyParcel || $hasVeloyd;

        if (! $hasLabel) {
            $labelAttrs['pdf_disk'] = 'dashed-ecommerce-core';
            $labelAttrs['pdf_path'] = 'print/test-page.pdf';
            $this->warn('  ! Geen verzendlabel (MyParcel/Veloyd) voor deze order → DYMO print de test-pagina.');
        } else {
            $carrier = $hasMyParcel ? 'MyParcel' : 'Veloyd';
            $this->line("  · {$carrier}-label gevonden → wordt on-demand opgehaald en geprint.");
        }

        $label = PrintJob::create($labelAttrs);
        $this->info("  ✔ Label-job {$label->ulid}");

        $this->newLine();
        $this->info('Klaar. De daemon op de Pi pakt beide binnen ~5 seconden op.');

        return self::SUCCESS;
    }
}
