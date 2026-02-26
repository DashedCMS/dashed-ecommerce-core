<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\CartResource\Pages;

use Dashed\DashedEcommerceCore\Models\Cart;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\CartResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class ListCarts extends ListRecords
{
    protected static string $resource = CartResource::class;

    protected function getTableQuery(): Builder|Relation|null
    {
        return Cart::query()->whereHas('items');
    }
}
