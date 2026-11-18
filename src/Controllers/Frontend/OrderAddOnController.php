<?php

namespace Dashed\DashedEcommerceCore\Controllers\Frontend;

use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderAddOn;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\OrderAddOnOffer;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\Offer;
use Dashed\DashedEcommerceCore\Classes\OrderAddOns\CheckoutMode;

/**
 * /bijbestellen/{order} (ondertekend, uit de bedankpagina, de orderbevestiging, de herinneringsmail en
 * de ordertracker): zet de bijbestel-modus in de sessie en toont voorstellen die zonder verzendkosten in
 * dezelfde doos meegaan. Is het aanbod dicht, dan zegt de pagina dat (nooit een 403 op een geldige link).
 */
class OrderAddOnController
{
    public function show(Request $request, int $order): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $order = Order::findOrFail($order);
        $reason = Offer::reason($order);
        $open = $reason === null;

        seo()->metaData('metaTitle', Translation::get('pagina-titel', 'bijbestellen', 'Nog iets vergeten?'));
        seo()->metaData('metaRobots', 'noindex');

        if ($open) {
            CheckoutMode::start($order);
            OrderAddOnOffer::countClick($order);
        } else {
            // Alleen de modus van deze order stoppen, niet die van een andere (nog open) order.
            $current = CheckoutMode::parent();

            if (! $current || (int) $current->id === (int) $order->id) {
                CheckoutMode::stop();
            }
        }

        $theme = config('dashed-core.site_theme', 'dashed').'.order-add-ons.show';

        return view(view()->exists($theme) ? $theme : 'dashed-ecommerce-core::order-add-ons.show', [
            'order' => $order,
            'open' => $open,
            'reason' => $reason,
            'orderNumber' => Offer::orderNumber($order),
            'untilText' => $open ? Offer::untilText($order) : '',
            'alreadyAdded' => OrderAddOn::query()->where('parent_order_id', $order->id)->whereNotNull('paid_at')->exists(),
        ]);
    }
}
