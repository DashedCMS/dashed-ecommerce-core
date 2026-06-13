<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedEcommerceCore\Models\OrderReturn;

class ReturnStatusController extends Controller
{
    public function show(string $hash)
    {
        $key = 'return-status:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429);
        }
        RateLimiter::hit($key, 60);

        $orderReturn = OrderReturn::query()
            ->with(['order', 'lines.orderProduct.product', 'lines.returnReason'])
            ->where('hash', $hash)
            ->first();

        if (! $orderReturn) {
            abort(404);
        }

        return view('dashed-ecommerce-core::return-status.show', [
            'orderReturn' => $orderReturn,
        ]);
    }

    public function downloadLabel(string $hash)
    {
        $orderReturn = OrderReturn::query()->where('hash', $hash)->first();

        if (! $orderReturn || ! $orderReturn->return_label_path) {
            abort(404);
        }

        return Storage::disk('public')->download($orderReturn->return_label_path);
    }
}
