<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\ViewRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderReturnResource;

class ViewOrderReturn extends ViewRecord
{
    protected static string $resource = OrderReturnResource::class;

    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::getEloquentQuery()
            ->with(['order', 'lines.orderProduct', 'lines.returnReason'])
            ->findOrFail($key);
    }
}
