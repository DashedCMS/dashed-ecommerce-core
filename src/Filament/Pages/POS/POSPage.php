<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Filament\Pages\Page;
use Dashed\DashedEcommerceCore\Filament\Resources\OrderResource\Concerns\CreateManualOrderActions;

class POSPage extends page
{
    use CreateManualOrderActions;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Point of Sale';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $title = 'Point of Sale';
    protected static ?string $slug = 'point-of-sale';
    protected static ?int $navigationSort = 3;
    protected ?string $maxContentWidth = 'full';

    protected static string $view = 'dashed-ecommerce-core::pos.pages.point-of-sale';

    public function mount(): void
    {
        $this->initialize('point-of-sale');
    }
}
