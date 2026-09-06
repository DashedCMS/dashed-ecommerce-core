<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

/**
 * De print-wachtrij uit het CMS (Print queue) voor de app: bekijken wat er
 * klaarstaat/geprint/mislukt is, en per taak opnieuw proberen of annuleren —
 * dezelfde acties als de Filament PrintJobResource. Het printen zelf blijft
 * bij de daemon (CUPS).
 */
class PrintJobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);
        $page = max(1, (int) $request->query('page', 1));

        $query = PrintJob::query()
            ->with(['order:id,invoice_id', 'printer:id,name'])
            ->orderByDesc('id');

        // Statusfilter (csv), bv. ?status=pending,failed. Zonder filter: alles.
        $statuses = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('status', '')))));
        $valid = array_map(fn (PrintJobStatus $s) => $s->value, PrintJobStatus::cases());
        $statuses = array_values(array_intersect($statuses, $valid));
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $rows->map(fn (PrintJob $job) => $this->payload($job))->all(),
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($total / $perPage)),
            ],
        ]);
    }

    /** Zet de taak terug in de wachtrij (zelfde als de Filament-actie). */
    public function retry(int $id): JsonResponse
    {
        $job = PrintJob::findOrFail($id);
        $job->retry();

        return response()->json(['data' => $this->payload($job->fresh(['order:id,invoice_id', 'printer:id,name']))]);
    }

    /** Annuleer de taak (zelfde als de Filament-actie). */
    public function cancel(int $id): JsonResponse
    {
        $job = PrintJob::findOrFail($id);
        $job->update(['status' => PrintJobStatus::Cancelled]);

        return response()->json(['data' => $this->payload($job->fresh(['order:id,invoice_id', 'printer:id,name']))]);
    }

    /** @return array<string, mixed> */
    private function payload(PrintJob $job): array
    {
        return [
            'id' => $job->id,
            'ulid' => $job->ulid,
            'type' => $job->type?->value,
            'order_id' => $job->order_id,
            'invoice_id' => $job->order?->invoice_id,
            'printer_name' => $job->printer?->name,
            'status' => $job->status?->value,
            'attempts' => (int) $job->attempts,
            'error_message' => $job->error_message,
            'created_at' => optional($job->created_at)->toIso8601String(),
            'printed_at' => optional($job->printed_at)->toIso8601String(),
        ];
    }
}
