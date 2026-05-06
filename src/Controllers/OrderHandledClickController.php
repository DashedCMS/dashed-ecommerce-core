<?php

namespace Dashed\DashedEcommerceCore\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderHandledClick;
use Dashed\DashedEcommerceCore\Models\OrderFlowEnrollment;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlowStep;

class OrderHandledClickController extends Controller
{
    public function click(Request $request, int $order, int $step)
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $orderModel = Order::find($order);
        $stepModel = OrderHandledFlowStep::with('flow')->find($step);

        if (! $orderModel || ! $stepModel) {
            abort(404);
        }

        $linkType = (string) ($request->query('type') ?: 'button');
        $to = (string) ($request->query('to') ?: '/');

        try {
            OrderHandledClick::create([
                'order_id' => $orderModel->id,
                'flow_step_id' => $stepModel->id,
                'link_type' => $linkType,
                'clicked_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('order-flow: click niet gelogd', [
                'order_id' => $orderModel->id,
                'flow_step_id' => $stepModel->id,
                'error' => $e->getMessage(),
            ]);
        }

        $flow = $stepModel->flow;
        if ($flow && $flow->cancel_on_link_click === true) {
            // Annuleer de specifieke flow-inschrijving voor deze (order, flow).
            OrderFlowEnrollment::query()
                ->where('order_id', $orderModel->id)
                ->where('flow_id', $flow->id)
                ->whereNull('cancelled_at')
                ->update([
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'link_click',
                    'updated_at' => now(),
                ]);

            // Backwards-compat: zet ook de legacy-kolom op de order zodat oude
            // code-paden die hierop checken correct blijven werken.
            if ($orderModel->handled_flow_cancelled_at === null) {
                $orderModel->forceFill(['handled_flow_cancelled_at' => now()])->save();
            }
        }

        // Beveiliging: alleen http(s) URL's of relatieve paden toestaan
        if (! $this->isSafeRedirect($to)) {
            $to = '/';
        }

        return redirect()->away($to);
    }

    protected function isSafeRedirect(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return (bool) preg_match('#^https?://#i', $url);
    }
}
