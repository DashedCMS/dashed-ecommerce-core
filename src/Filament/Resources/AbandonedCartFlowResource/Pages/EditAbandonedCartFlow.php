<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedEcommerceCore\Models\AbandonedCartFlow;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource;
use Dashed\DashedEcommerceCore\Filament\Resources\AbandonedCartFlowResource\Widgets\AbandonedCartFlowStats;

class EditAbandonedCartFlow extends EditRecord
{
    protected static string $resource = AbandonedCartFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AbandonedCartFlowStats::class,
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record->is_active) {
            AbandonedCartFlow::where('id', '!=', $this->record->id)->update(['is_active' => false]);
        }
    }
}
