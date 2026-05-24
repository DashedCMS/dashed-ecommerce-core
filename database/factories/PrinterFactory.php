<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Database\Factories;

use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Models\Printer;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrinterFactory extends Factory
{
    protected $model = Printer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'location' => $this->faker->city(),
            'type' => PrinterType::PackingSlip,
            'max_retries' => 3,
            'is_active' => true,
            'last_ping_at' => null,
        ];
    }
}
