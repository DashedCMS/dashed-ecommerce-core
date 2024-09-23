<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use Filament\Pages\Page;

class POSPageRedirect extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Point of Sale';
    protected static ?string $navigationGroup = 'E-commerce';
    protected static ?string $title = 'Point of Sale';
    protected static ?string $slug = 'point-of-sale';
    protected static ?int $navigationSort = 3;
    protected ?string $maxContentWidth = 'full';

    public static function getNavigationUrl(): string
    {
        return route('dashed.ecommerce.point-of-sale');
    }
}
