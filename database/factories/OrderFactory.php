<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Database\Factories;

use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $totaal = 100.00; // incl. btw, 21%

        return [
            'site_id' => Sites::getFirstSite()['id'],
            'status' => 'paid',
            'fulfillment_status' => 'unhandled',
            'retour_status' => 'unhandled',
            'order_origin' => 'own',
            'invoice_id' => 'INV-SURF-'.strtoupper(Str::random(6)),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'street' => 'Teststraat',
            'house_nr' => '1',
            'zip_code' => '1234AB',
            'city' => 'Teststad',
            'country' => 'Nederland',
            'total' => $totaal,
            'subtotal' => round($totaal / 1.21, 2),
            'btw' => round($totaal - $totaal / 1.21, 2),
            'discount' => 0,
            'ip' => '127.0.0.1',
        ];
    }

    /**
     * Concept-order zonder factuurnummer: een concept telt nog geen omzet
     * en heeft dus nog geen factuur gekregen.
     */
    public function concept(): static
    {
        return $this->state(fn () => [
            'status' => Order::STATUS_CONCEPT,
            'invoice_id' => null,
        ]);
    }

    public function wachtOpBevestiging(): static
    {
        return $this->state(fn () => ['status' => 'waiting_for_confirmation']);
    }

    public function proforma(): static
    {
        return $this->state(fn () => [
            'status' => 'pending',
            'is_proforma' => true,
            'invoice_id' => 'PROFORMA',
        ]);
    }

    public function metProducten(int $n = 2): static
    {
        return $this->has(OrderProduct::factory()->count($n), 'orderProducts');
    }

    public function betaald(): static
    {
        return $this->has(
            OrderPayment::factory()->state(fn (array $attrs, Order $order) => ['amount' => $order->total]),
            'orderPayments'
        );
    }
}
