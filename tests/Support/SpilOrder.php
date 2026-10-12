<?php

use Illuminate\Support\Facades\Schema;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

if (! function_exists('spilOrder')) {
    /**
     * Betaalde order van EUR 100 incl. 21% btw: Shirt 3 x EUR 20 en Broek 1 x EUR 40.
     * Geeft ['order', 'shirt', 'broek'] terug; $attrs overschrijft orderkolommen.
     */
    function spilOrder(array $attrs = []): array
    {
        // De product-id's van de orderregels bestaan niet.
        Schema::disableForeignKeyConstraints();

        $order = Order::create($attrs + [
            'email' => 'klant@example.com',
            'status' => 'paid',
            'invoice_id' => 'INV-' . strtoupper(uniqid()),
            'subtotal' => 100,
            'total' => 100,
            'btw' => 17.36,
        ]);
        $shirt = OrderProduct::create(['order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 3, 'price' => 60, 'btw' => 10.41, 'vat_rate' => 21]);
        $broek = OrderProduct::create(['order_id' => $order->id, 'name' => 'Broek', 'quantity' => 1, 'price' => 40, 'btw' => 6.94, 'vat_rate' => 21]);

        return ['order' => $order->fresh(), 'shirt' => $shirt, 'broek' => $broek];
    }
}
