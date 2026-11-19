<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductReviewResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductReviewResource;

class ListProductReviews extends ListRecords
{
    protected static string $resource = ProductReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
