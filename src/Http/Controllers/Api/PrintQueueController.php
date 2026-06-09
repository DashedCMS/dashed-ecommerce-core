<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api;

use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Http\Requests\MarkJobFailedRequest;
use Dashed\DashedEcommerceCore\Http\Resources\PrintJobResource;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PrintQueueController extends Controller
{
    public function pending(Request $request): JsonResponse
    {
        $printer = $this->printerFrom($request);

        $allowedTypes = $printer->type === PrinterType::Both
            ? [PrintJobType::PackingSlip->value, PrintJobType::ShippingLabel->value]
            : [$printer->type->value];

        $jobs = PrintJob::query()
            ->where('status', PrintJobStatus::Pending->value)
            ->whereIn('type', $allowedTypes)
            ->where(fn ($q) => $q->whereNull('printer_id')->orWhere('printer_id', $printer->id))
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        return response()->json(PrintJobResource::collection($jobs)->toArray($request));
    }

    public function claim(string $ulid, Request $request): JsonResponse
    {
        $printer = $this->printerFrom($request);

        return DB::transaction(function () use ($printer, $ulid, $request) {
            $allowedTypes = $printer->type === PrinterType::Both
                ? [PrintJobType::PackingSlip->value, PrintJobType::ShippingLabel->value]
                : [$printer->type->value];

            $job = PrintJob::query()
                ->where('ulid', $ulid)
                ->where('status', PrintJobStatus::Pending->value)
                ->whereIn('type', $allowedTypes)
                ->where(fn ($q) => $q->whereNull('printer_id')->orWhere('printer_id', $printer->id))
                ->lockForUpdate()
                ->first();

            if (! $job) {
                return response()->json(['message' => 'Job niet meer beschikbaar'], Response::HTTP_CONFLICT);
            }

            $job->markAsClaimed($printer);

            return response()->json((new PrintJobResource($job))->toArray($request));
        });
    }

    public function done(string $ulid, Request $request): JsonResponse
    {
        $job = PrintJob::where('ulid', $ulid)->firstOrFail();
        $job->markAsDone();

        return response()->json(['status' => 'done']);
    }

    public function failed(string $ulid, MarkJobFailedRequest $request): JsonResponse
    {
        $job = PrintJob::where('ulid', $ulid)->firstOrFail();
        $job->markAsFailed($request->validated()['error_message']);

        return response()->json(['status' => 'failed']);
    }

    public function pdf(string $ulid)
    {
        $job = PrintJob::where('ulid', $ulid)->firstOrFail();

        // Test-print: de PDF is een asset in de package zelf (geen Storage-disk).
        // 'dashed-ecommerce-core' is de package-naam, gebruikt als sentinel.
        if ($job->pdf_disk === 'dashed-ecommerce-core') {
            $path = __DIR__ . '/../../../../resources/' . ltrim((string) $job->pdf_path, '/');
            abort_unless(is_file($path), 404, 'Test-PDF ontbreekt');

            return response()->file($path, ['Content-Type' => 'application/pdf']);
        }

        // Verzendlabel zonder opgeslagen PDF (bv. handmatige/automatische print):
        // los 'm on-demand op en download 'm zo nodig uit MyParcel.
        if ($job->type === PrintJobType::ShippingLabel && ! $job->pdf_path && $job->order_id) {
            if (class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)) {
                $mp = \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::where('order_id', $job->order_id)
                    ->whereNotNull('shipment_id')
                    ->latest()
                    ->first();
                if ($mp) {
                    $path = $mp->label_pdf_path;
                    // Nog niet lokaal gedownload? Haal 'm nu op bij MyParcel. Een
                    // fout hier mag de Veloyd-route hieronder niet blokkeren.
                    if ((! $path || ! Storage::disk('public')->exists($path))
                        && class_exists(\Dashed\DashedEcommerceMyParcel\Classes\MyParcel::class)) {
                        try {
                            $path = \Dashed\DashedEcommerceMyParcel\Classes\MyParcel::downloadLabelForOrder($mp);
                        } catch (\Throwable $e) {
                            report($e);
                            $path = null;
                        }
                    }
                    if ($path && Storage::disk('public')->exists($path)) {
                        return Storage::disk('public')->response($path);
                    }
                }
            }

            if (class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)) {
                // Veloyd slaat het label-PDF op de public disk op (label_pdf_path);
                // het veld label_url wordt niet gebruikt.
                $v = \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::where('order_id', $job->order_id)
                    ->whereNotNull('label_pdf_path')
                    ->latest()
                    ->first();
                if ($v && $v->label_pdf_path && Storage::disk('public')->exists($v->label_pdf_path)) {
                    return Storage::disk('public')->response($v->label_pdf_path);
                }
            }
        }

        if ($job->type === PrintJobType::PackingSlip && ! $job->pdf_path && $job->order) {
            $job->order->createPackingSlip();
            $job->update([
                'pdf_disk' => 'dashed',
                'pdf_path' => $job->order->packingSlipPath(),
            ]);
        }

        abort_unless(
            $job->pdf_disk && Storage::disk($job->pdf_disk)->exists($job->pdf_path),
            404,
            'PDF ontbreekt'
        );

        return Storage::disk($job->pdf_disk)->response($job->pdf_path);
    }

    public function ping(Request $request): JsonResponse
    {
        $printer = $this->printerFrom($request);

        return response()->json([
            'pong' => true,
            'printer' => $printer->ulid,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    private function printerFrom(Request $request): Printer
    {
        $printer = $request->attributes->get('printer');
        abort_unless($printer instanceof Printer, 403);

        return $printer;
    }
}
