<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource;

class CreateAbandonedCartFlow extends CreateRecord
{
    protected static string $resource = AbandonedCartFlowResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->is_active) {
            AbandonedCartFlow::where('id', '!=', $this->record->id)->update(['is_active' => false]);
        }
    }
}
