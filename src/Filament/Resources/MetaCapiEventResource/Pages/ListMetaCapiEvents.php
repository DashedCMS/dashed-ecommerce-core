<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\MetaCapiEventResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedEcommerceCore\Filament\Resources\MetaCapiEventResource;
use Dashed\DashedEcommerceCore\Filament\Widgets\MetaCapiEventCountsWidget;

class ListMetaCapiEvents extends ListRecords
{
    protected static string $resource = MetaCapiEventResource::class;

    /** Dagtellers van ViewContent, AddToCart en InitiateCheckout: die staan niet per stuk in de tabel. */
    protected function getHeaderWidgets(): array
    {
        return [MetaCapiEventCountsWidget::class];
    }
}
