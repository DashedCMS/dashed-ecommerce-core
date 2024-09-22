<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Dashed\DashedEcommerceCore\Models\Product;

class POSPage extends page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Point of Sale';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $title = 'Point of Sale';
    protected static ?string $slug = 'point-of-sale';
    protected static ?int $navigationSort = 3;
    protected ?string $maxContentWidth = 'full';

    protected static string $view = 'dashed-ecommerce-core::pos.pages.point-of-sale';

    public Collection $products;

    public function mount(): void
    {
        $this->products = Product::publicShowable()->thisSite()->get();
    }

    public function updated()
    {
    }
}
