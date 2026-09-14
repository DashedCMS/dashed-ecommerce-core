<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\ViewRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Actions\ReturnActions;

class ViewOrderReturn extends ViewRecord
{
    protected static string $resource = OrderReturnResource::class;

    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()
            ->with(['order', 'creditOrder', 'lines.orderProduct', 'lines.returnReason'])
            ->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return ReturnActions::all();
    }
}
