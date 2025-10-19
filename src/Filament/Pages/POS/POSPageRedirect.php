<?php

namespace Dashed\DashedEcommerceCore\Filament\Pages\POS;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

class POSPageRedirect extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Point of Sale';
    protected static string | UnitEnum | null $navigationGroup = 'E-commerce';
    protected static ?string $title = 'Point of Sale';
    protected static ?string $slug = 'point-of-sale';
    protected static ?int $navigationSort = 3;
    protected Width | string | null $maxContentWidth = 'full';

    public static function getNavigationUrl(): string
    {
        return route('dashed.ecommerce.point-of-sale');
    }
}
