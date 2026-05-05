<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\OrderHandledFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderHandledFlowResource;

class CreateOrderHandledFlow extends CreateRecord
{
    protected static string $resource = OrderHandledFlowResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->is_active) {
            OrderHandledFlow::query()
                ->where('id', '!=', $this->record->id)
                ->update(['is_active' => false]);
        }
    }
}
