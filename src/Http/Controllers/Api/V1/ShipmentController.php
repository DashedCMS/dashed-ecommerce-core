<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Http\Controllers\Api\V1\Concerns\MapsCarrierLabelStatus;

/**
 * Verzend-hub: één overzicht van alle zendingen over de carriers heen
 * (Veloyd + MyParcel), site-bewust, met dezelfde genormaliseerde status-badges
 * als de per-order labellijst. Plus een retourlabel-actie per order.
 */
class ShipmentController extends Controller
{
    use MapsCarrierLabelStatus;

    private const CARRIERS = [
        'veloyd' => [
            'name' => 'Veloyd',
            'model' => \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class,
        ],
        'myparcel' => [
            'name' => 'MyParcel',
            'model' => \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class,
        ],
    ];

    /**
     * Geaggregeerd overzicht van zendingen voor de actieve site, nieuwste eerst,
     * gepagineerd. Filters: status (genormaliseerd), carrier, is_return, periode.
     */
    public function index(Request $request): JsonResponse
    {
        $siteId = Sites::getActive();
        $statusFilter = trim((string) $request->query('status'));
        $carrierFilter = trim((string) $request->query('carrier'));
        $isReturn = $request->query('is_return');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        // Verzamel uit beide carrier-tabellen de rijen die bij een order van de
        // actieve site horen (join via order_id → orders.site_id). We mappen elke
        // rij naar het uniforme item-formaat en sorteren daarna over alle carriers
        // heen op created_at (nieuwste eerst), zodat de paginering klopt.
        $items = new Collection();

        foreach (self::CARRIERS as $carrierKey => $carrier) {
            if ($carrierFilter !== '' && $carrierFilter !== $carrierKey) {
                continue;
            }
            $model = $carrier['model'];
            if (! class_exists($model)) {
                continue;
            }

            $query = $model::query()
                ->whereNotNull('shipment_id')
                ->whereHas('order', fn ($q) => $q->where('site_id', $siteId))
                ->with('order:id,invoice_id,first_name,last_name,company_name,site_id,created_at')
                ->latest();

            if ($isReturn !== null && $isReturn !== '') {
                $query->where('is_return', (bool) ((int) $isReturn));
            }
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            foreach ($query->get() as $row) {
                $status = $this->labelStatus($row);
                if ($statusFilter !== '' && $status['key'] !== $statusFilter) {
                    continue;
                }

                $items->push([
                    'id' => (int) $row->id,
                    'carrier' => $carrierKey,
                    'carrier_name' => $row->carrier ?: $carrier['name'],
                    'order_id' => (int) $row->order_id,
                    'invoice_id' => $row->order?->invoice_id,
                    'customer_name' => $row->order?->name,
                    'track_trace' => $this->trackTraceList($row->track_and_trace),
                    'status' => $status['key'],
                    'status_label' => $status['label'],
                    'status_tone' => $status['tone'],
                    'is_return' => (bool) $row->is_return,
                    'created_at' => optional($row->created_at)->toIso8601String(),
                    '_sort' => optional($row->created_at)->getTimestamp() ?? 0,
                ]);
            }
        }

        $sorted = $items->sortByDesc('_sort')->values();

        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);
        $page = max(1, (int) $request->query('page', 1));
        $total = $sorted->count();
        $pageItems = $sorted->slice(($page - 1) * $perPage, $perPage)
            ->map(fn (array $item) => collect($item)->except('_sort')->all())
            ->values();

        return response()->json([
            'data' => $pageItems,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * Maak een retourlabel aan voor de order via de bestaande carrier-pad
     * (MyParcel::createReturnLabelForOrder / Veloyd::createReturnLabelForOrder).
     * We kiezen de carrier op basis van een bestaande (forward) carrier-rij van
     * de order; anders de eerste geconfigureerde provider. Geld-neutraal: dit
     * maakt enkel een label aan, geen terugbetaling.
     */
    public function returnLabel(Request $request, int $order): JsonResponse
    {
        $model = Order::thisSite()->findOrFail($order);

        $resolved = $this->resolveReturnCarrier($model);
        if ($resolved === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Geen verzendprovider geconfigureerd voor deze site, of geen carrier bekend op de bestelling.',
            ], 422);
        }

        [$carrierKey, $carrierRow] = $resolved;

        try {
            $result = $this->createReturnLabelVia($carrierKey, $carrierRow);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'carrier' => $carrierKey,
                'message' => $e->getMessage(),
            ], 422);
        }

        activity()->performedOn($model)->causedBy($request->user())
            ->withProperties(['carrier' => $carrierKey])
            ->log('mobile-api: retourlabel aangemaakt');

        return response()->json([
            'ok' => true,
            'carrier' => $carrierKey,
            'message' => 'Retourlabel aangemaakt via ' . self::CARRIERS[$carrierKey]['name'] . '.',
            'track_trace' => $this->trackTraceList($result['row']->track_and_trace),
        ]);
    }

    /**
     * Bepaal welke carrier het retourlabel maakt: de carrier van een bestaande
     * (niet-retour) verzending van deze order, met carrier/pakkettype/verzendtype
     * uit die rij. We dupliceren die instellingen in een nieuwe retour-rij (zoals
     * de Filament-actie doet) en geven [carrierKey, nieuwe carrier-rij] terug.
     *
     * @return array{0: string, 1: \Illuminate\Database\Eloquent\Model}|null
     */
    private function resolveReturnCarrier(Order $model): ?array
    {
        foreach (self::CARRIERS as $carrierKey => $carrier) {
            $modelClass = $carrier['model'];
            if (! class_exists($modelClass)) {
                continue;
            }

            $source = $modelClass::where('order_id', $model->id)
                ->where('is_return', false)
                ->whereNotNull('carrier')
                ->latest()
                ->first();

            if (! $source) {
                continue;
            }

            // De carrier-order-relaties op Order zijn dynamisch geregistreerd
            // (addDynamicRelation) en zijn dus niet via method_exists te
            // detecteren; we maken de retour-rij daarom direct op het model aan
            // (zelfde velden als de Filament-retouractie).
            $returnRow = $modelClass::create([
                'order_id' => $model->id,
                'carrier' => $source->carrier,
                'package_type' => $source->package_type,
                'delivery_type' => $source->delivery_type,
                'is_return' => true,
            ]);

            return [$carrierKey, $returnRow];
        }

        return null;
    }

    /**
     * @return array{row: \Illuminate\Database\Eloquent\Model}
     */
    private function createReturnLabelVia(string $carrierKey, $carrierRow): array
    {
        if ($carrierKey === 'veloyd') {
            \Dashed\DashedEcommerceVeloyd\Classes\Veloyd::createReturnLabelForOrder($carrierRow);
        } else {
            \Dashed\DashedEcommerceMyParcel\Classes\MyParcel::createReturnLabelForOrder($carrierRow);
        }

        return ['row' => $carrierRow->fresh()];
    }
}
