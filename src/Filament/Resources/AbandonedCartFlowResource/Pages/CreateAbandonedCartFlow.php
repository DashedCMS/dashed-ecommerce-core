<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource;

class CreateAbandonedCartFlow extends CreateRecord
{
    protected static string $resource = AbandonedCartFlowResource::class;

    protected function afterCreate(): void
    {
        if ($this->record->is_active) {
            $this->record->deactivateOverlappingFlows();
        }
    }
}
