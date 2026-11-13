<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\CheckoutAbandonment;

/**
 * Pre-order uitval: checkouts die strandden vóór order-creatie (verlaten
 * winkelmandjes / afrekenpogingen). Site-gescoped, aflopend op tijd. Read-only.
 */
class CheckoutAbandonmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) config('dashed-mobile-api.default_page_size', 25);

        $query = CheckoutAbandonment::query()
            ->where('site_id', Sites::getActive())
            ->when(
                trim((string) $request->query('reason', '')) !== '',
                fn ($q) => $q->where('reason', (string) $request->query('reason')),
            )
            ->orderByDesc('created_at');

        $page = $query->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn (CheckoutAbandonment $a) => [
                'id' => (int) $a->id,
                'email' => $a->email,
                'reason' => $a->reason,
                'cart_total' => $a->cart_total !== null ? (float) $a->cart_total : null,
                'cart_id' => $a->cart_id !== null ? (int) $a->cart_id : null,
                'created_at' => optional($a->created_at)->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
