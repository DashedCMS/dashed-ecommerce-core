<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Database\Factories;

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderProductFactory extends Factory
{
    protected $model = OrderProduct::class;

    public function definition(): array
    {
        return [
            'product_id' => null,
            'name' => $this->faker->words(2, true),
            'sku' => strtoupper(Str::random(8)),
            'quantity' => 1,
            'price' => 50.00,
            'vat_rate' => 21,
        ];
    }
}
