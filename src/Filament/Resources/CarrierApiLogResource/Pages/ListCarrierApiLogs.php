<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CarrierApiLogResource\Pages;

use Filament\Tables\Table;
use Livewire\Attributes\Url;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Filament\Resources\CarrierApiLogResource;

class ListCarrierApiLogs extends ListRecords
{
    protected static string $resource = CarrierApiLogResource::class;

    /** ?order=123 narrows the list to one order (link from the order page). */
    #[Url(as: 'order')]
    public ?int $orderId = null;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->modifyQueryUsing(fn (Builder $query) => $this->orderId ? $query->where('order_id', $this->orderId) : $query);
    }
}
