<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource\Pages;

use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductFilterOptionResource;

class EditProductFilterOption extends EditRecord
{
    use Translatable;

    protected static string $resource = ProductFilterOptionResource::class;

    protected function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        array_shift($breadcrumbs);
        $breadcrumbs = array_merge([route('filament.resources.product-filters.edit', [$this->record->productFilter->id]) => "Product filter {$this->record->productFilter->name}"], $breadcrumbs);

        return $breadcrumbs;
    }
}
