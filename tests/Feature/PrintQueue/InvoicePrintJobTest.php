<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Illuminate\Support\Facades\Storage;

/**
 * Facturen door de CUPS-print-queue. Een factuur is een A4-document en gaat naar
 * dezelfde document-printer (PrinterType::PackingSlip/Both) als de pakbon; er is
 * geen apart factuur-printertype. Spiegelt de bestaande pakbon-print-flow.
 */
function makeInvoiceOrder(string $siteId = 'site', array $overrides = []): Order
{
    $order = Order::create(array_merge([
        'invoice_id' => 'INV-' . uniqid(),
        'status' => 'paid',
        'order_origin' => 'own',
        'email' => 'klant@example.com',
        'first_name' => 'Jan',
        'last_name' => 'Jansen',
        'total' => 10,
    ], $overrides));

    // site_id zit niet in $fillable in alle paden; expliciet zetten.
    $order->site_id = $siteId;
    $order->save();

    return $order;
}

it('creates an Invoice PrintJob via OrderController::print with type=invoice', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'sanctum');

    Printer::factory()->create(['type' => PrinterType::PackingSlip, 'is_active' => true]);
    $order = makeInvoiceOrder();

    $this->postJson("/api/v1/orders/{$order->id}/print", [
        'type' => 'invoice',
    ], ['X-Site-Id' => 'site'])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(PrintJob::count())->toBe(1);
    $job = PrintJob::first();
    expect($job->type)->toBe(PrintJobType::Invoice)
        ->and($job->order_id)->toBe($order->id)
        ->and($job->status)->toBe(PrintJobStatus::Pending);
});

it('lets a document printer claim Invoice jobs from the queue', function () {
    $order = makeInvoiceOrder();
    $printer = Printer::factory()->create(['type' => PrinterType::PackingSlip, 'is_active' => true]);
    $job = PrintJob::factory()->create([
        'type' => PrintJobType::Invoice,
        'order_id' => $order->id,
        'status' => PrintJobStatus::Pending,
    ]);

    $token = $printer->createToken('t')->plainTextToken;

    // Pending-overzicht bevat de factuur-job.
    $pending = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/print/pending')
        ->assertOk()
        ->json();
    expect(collect($pending)->pluck('ulid'))->toContain($job->ulid);

    // En de printer kan 'm claimen.
    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/print/{$job->ulid}/claim")
        ->assertOk();

    expect($job->fresh()->status)->toBe(PrintJobStatus::Claimed);
});

it('does not let a shipping-label-only printer claim Invoice jobs', function () {
    $order = makeInvoiceOrder();
    $printer = Printer::factory()->create(['type' => PrinterType::ShippingLabel, 'is_active' => true]);
    $job = PrintJob::factory()->create([
        'type' => PrintJobType::Invoice,
        'order_id' => $order->id,
        'status' => PrintJobStatus::Pending,
    ]);

    $token = $printer->createToken('t')->plainTextToken;

    $pending = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/print/pending')
        ->assertOk()
        ->json();
    expect(collect($pending)->pluck('ulid'))->not->toContain($job->ulid);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->postJson("/api/print/{$job->ulid}/claim")
        ->assertStatus(409);
});

it('generates and serves the invoice PDF for an Invoice job', function () {
    $order = makeInvoiceOrder();

    // Leg de factuur-PDF alvast neer op het pad waar createInvoice() schrijft,
    // zodat createInvoice() een no-op is (geen dompdf-render in de test) en de
    // queue-controller exact dat bestand serveert.
    Storage::disk('dashed')->put(ltrim($order->invoicePath(), '/'), '%PDF-1.4 fake');

    $printer = Printer::factory()->create(['type' => PrinterType::PackingSlip, 'is_active' => true]);
    $job = PrintJob::factory()->create([
        'type' => PrintJobType::Invoice,
        'order_id' => $order->id,
        'status' => PrintJobStatus::Claimed,
        'printer_id' => $printer->id,
        'pdf_disk' => null,
        'pdf_path' => null,
    ]);

    $token = $printer->createToken('t')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->get("/api/print/{$job->ulid}/pdf")
        ->assertOk();

    // De job wijst nu naar de gegenereerde factuur-PDF op de 'dashed'-disk.
    $job->refresh();
    expect($job->pdf_disk)->toBe('dashed')
        ->and(Storage::disk($job->pdf_disk)->exists($job->pdf_path))->toBeTrue();
});
