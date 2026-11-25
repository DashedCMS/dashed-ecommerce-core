<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Dashed\DashedCore\Models\User;
use Illuminate\Routing\Controller;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

/**
 * Printstatus per orderregel: een regel klaar of weer niet klaar melden.
 * Idempotent; de modelmethodes loggen op de order en vuren de events.
 */
class OrderProductProductionController extends Controller
{
    public function update(Request $request, int $orderProduct): JsonResponse
    {
        $data = $request->validate([
            'ready' => ['required', 'boolean'],
        ]);

        $line = OrderProduct::query()
            ->whereHas('order', fn ($q) => $q->thisSite())
            ->findOrFail($orderProduct);

        $user = $request->user();
        $user = $user instanceof User ? $user : null;

        if ($request->boolean('ready')) {
            $line->markProductionReady($user);
        } else {
            $line->markProductionNotReady($user);
        }

        $line->loadMissing('productionReadyBy');

        return response()->json([
            'data' => [
                'id' => $line->id,
                ...$line->productionReadyPayload(),
            ],
        ]);
    }
}
