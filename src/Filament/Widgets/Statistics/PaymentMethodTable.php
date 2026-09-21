<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Statistics;

use Filament\Widgets\Widget;

class PaymentMethodTable extends Widget
{
    protected string $view = 'dashed-ecommerce-core::filament.widgets.payment-method-table';

    protected int | string | array $columnSpan = 'full';

    protected $listeners = [
        'updateGraphData' => 'updateGraphData',
    ];

    public $graphData;

    public function updateGraphData($data): void
    {
        $this->graphData = $data;
    }
}
