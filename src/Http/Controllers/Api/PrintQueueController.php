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

        if ($job->type === PrintJobType::PackingSlip && ! $job->pdf_path) {
            $job->order->generatePackingSlip();
            $job->update([
                'pdf_disk' => 'dashed',
                'pdf_path' => 'dashed/packing-slips/packing-slip-'
                    . ($job->order->invoice_id ?: $job->order->id)
                    . '-' . $job->order->hash . '.pdf',
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
