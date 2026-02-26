<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedEcommerceCore\Models\Cart;
use Illuminate\Database\Eloquent\Relations\Relation;
use Dashed\DashedEcommerceCore\Filament\Resources\CartResource;

class ListCarts extends ListRecords
{
    protected static string $resource = CartResource::class;

    protected function getTableQuery(): Builder|Relation|null
    {
        return Cart::query()->whereHas('items');
    }
}
