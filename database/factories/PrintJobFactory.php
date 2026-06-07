<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Database\Factories;

use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

class PrintJobFactory extends Factory
{
    protected $model = PrintJob::class;

    public function definition(): array
    {
        return [
            'type' => PrintJobType::PackingSlip,
            'order_id' => null,
            'status' => PrintJobStatus::Pending,
            'attempts' => 0,
        ];
    }
}
