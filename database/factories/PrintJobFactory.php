<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Database\Factories;

use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Illuminate\Database\Eloquent\Factories\Factory;

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
