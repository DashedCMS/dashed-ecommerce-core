<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Classes\ConceptOrderService;

/**
 * Concept-orders ("parkeren") voor de mobiele kassa — hergebruikt de bestaande
 * ConceptOrderService (zelfde logica als de web-kassa).
 */
class PosConceptController extends Controller
{
    /** Sla de huidige cart op als concept. */
    public function save(Request $request): JsonResponse
    {
        $posCart = POSCart::where('identifier', (string) $request->input('posIdentifier'))->first();

        if (! $posCart || empty($posCart->products ?? [])) {
            return response()->json(['message' => 'Geen producten in de kassa.'], 422);
        }

        $existing = $posCart->loaded_concept_order_id
            ? Order::find($posCart->loaded_concept_order_id)
            : null;

        $order = ConceptOrderService::saveAsConcept($posCart, $request->user(), $existing);

        $posCart->refresh();
        $posCart->loaded_concept_order_id = null;
        $posCart->save();

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }

    /** Lijst van openstaande concepten voor de actieve site. */
    public function index(Request $request): JsonResponse
    {
        $concepts = Order::concept()
            ->where('site_id', (string) Sites::getActive())
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $concepts->map(fn (Order $o): array => [
                'id' => $o->id,
                'label' => $o->invoice_id ?: ('Concept #' . $o->id),
                'total' => $o->total !== null ? (float) $o->total : null,
                'customer_name' => trim((string) ($o->first_name . ' ' . $o->last_name)) ?: null,
                'created_at' => optional($o->created_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    /** Laad een concept terug in de kassa (hydrateert de cart). */
    public function load(Request $request): JsonResponse
    {
        $order = Order::concept()->where('site_id', (string) Sites::getActive())->find((int) $request->input('orderId'));
        $posCart = POSCart::where('identifier', (string) $request->input('posIdentifier'))->first();

        if (! $order || ! $posCart) {
            return response()->json(['message' => 'Concept of kassa-sessie niet gevonden.'], 404);
        }

        $posCart->products = [];
        $posCart->loaded_concept_order_id = $order->id;
        $posCart->save();

        ConceptOrderService::hydrate($posCart, $order);

        return response()->json(['success' => true]);
    }
}
