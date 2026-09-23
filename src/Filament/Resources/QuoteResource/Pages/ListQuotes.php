<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\QuoteResource;

class ListQuotes extends ListRecords
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label(__('Nieuwe offerte'))];
    }
}
