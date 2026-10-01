<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Database\Factories;

use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderPaymentFactory extends Factory
{
    protected $model = OrderPayment::class;

    public function definition(): array
    {
        return [
            'psp' => 'own',
            'payment_method' => 'iDEAL',
            'status' => 'paid',
            'amount' => 100.00,
        ];
    }
}
