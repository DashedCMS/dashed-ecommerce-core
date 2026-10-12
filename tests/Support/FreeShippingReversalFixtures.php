<?php

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversal;
use Dashed\DashedEcommerceCore\Services\OrderReturn\FreeShippingReversalProposal;

require_once __DIR__ . '/SpilOrder.php';

if (! function_exists('reversalFixture')) {
    /**
     * spilOrder() (Shirt 3 x 20, Broek 1 x 40, totaal 100) in Nederland, gratis
     * verzonden vanaf 60, met "Verzenden" 7,95 voor alles daaronder.
     */
    function reversalFixture(array $orderAttrs = [], float $paidCosts = 7.95): array
    {
        Customsetting::set('returns_charge_shipping_below_threshold', '1');
        Customsetting::set('returns_charge_shipping_from', now()->subDay()->toDateString());

        $zone = ShippingZone::create(['site_id' => Sites::getActive(), 'name' => ['nl' => 'Nederland'], 'zones' => ['Netherlands'], 'search_fields' => 'Netherlands']);
        $free = ShippingMethod::create(['shipping_zone_id' => $zone->id, 'name' => ['nl' => 'Gratis verzending'], 'sort' => 'free_delivery', 'costs' => 0, 'minimum_order_value' => 60, 'maximum_order_value' => 100000, 'order' => 1]);
        $paid = ShippingMethod::create(['shipping_zone_id' => $zone->id, 'name' => ['nl' => 'Verzenden'], 'sort' => 'static_amount', 'costs' => $paidCosts, 'minimum_order_value' => 0, 'maximum_order_value' => 59.99, 'order' => 2]);
        $pickup = ShippingMethod::create(['shipping_zone_id' => $zone->id, 'name' => ['nl' => 'Ophalen'], 'sort' => 'take_away', 'costs' => 0, 'minimum_order_value' => 0, 'maximum_order_value' => 100000, 'order' => 3]);

        $f = spilOrder($orderAttrs + ['country' => 'Netherlands', 'shipping_method_id' => $free->id, 'free_shipping_threshold' => 60]);

        return $f + compact('zone', 'free', 'paid', 'pickup');
    }
}

if (! function_exists('proposeReversal')) {
    function proposeReversal(array $f, array $quantities): ?FreeShippingReversalProposal
    {
        return app(FreeShippingReversal::class)->propose($f['order']->fresh(), $quantities);
    }
}
