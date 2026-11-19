<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductQuestionResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductQuestionResource;

class ListProductQuestions extends ListRecords
{
    protected static string $resource = ProductQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
