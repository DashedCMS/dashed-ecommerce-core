<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductGroupResource;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedCore\Filament\Concerns\HasCreatableCMSActions;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

class CreateProductGroup extends CreateRecord
{
    use HasCreatableCMSActions;

    protected static string $resource = ProductGroupResource::class;

    protected function getActions(): array
    {
        return self::CMSActions();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_ids'] = $data['site_ids'] ?? (isset($data['parent_id']) && $data['parent_id'] ? Product::find($data['parent_id'])->site_ids : [Sites::getFirstSite()['id']]);

        return $data;
    }
}
