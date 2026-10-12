<?php

use Illuminate\Support\Str;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderProduct;

if (! function_exists('spilOrder')) {
    /**
     * Een betaalde bestelling met twee regels voor de retourtests. Let op:
     * OrderProduct.price is een regeltotaal: Shirt is 3 x 20 = 60.
     * Zelfde body als spilOrder() in de monorepo-tests/Pest.php.
     */
    function spilOrder(array $attrs = []): array
    {
        Customsetting::set('taxes_prices_include_taxes', 1);

        $order = Order::create(array_merge([
            'email' => 'klant@example.com',
            'first_name' => 'Kim',
            'last_name' => 'Jansen',
            'status' => 'paid',
            'invoice_id' => 'INV-SPIL-'.strtoupper(Str::random(5)),
            'subtotal' => 100,
            'total' => 100,
            'btw' => 17.36,
            'fulfillment_status' => 'handled',
        ], $attrs));

        $shirt = OrderProduct::withoutEvents(fn () => OrderProduct::create([
            'order_id' => $order->id, 'name' => 'Shirt', 'quantity' => 3, 'price' => 60, 'vat_rate' => 21, 'sku' => 'SHIRT',
        ]));
        $broek = OrderProduct::withoutEvents(fn () => OrderProduct::create([
            'order_id' => $order->id, 'name' => 'Broek', 'quantity' => 1, 'price' => 40, 'vat_rate' => 21, 'sku' => 'BROEK',
        ]));

        $order->orderPayments()->create(['status' => 'paid', 'amount' => 100, 'psp' => 'own', 'payment_method' => 'iDEAL']);

        return ['order' => $order->fresh(), 'shirt' => $shirt, 'broek' => $broek];
    }
}
