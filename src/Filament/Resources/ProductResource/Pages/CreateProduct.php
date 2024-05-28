<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ProductResource\Pages;

use Dashed\DashedCore\Filament\Concerns\HasCreatableCMSActions;
use Illuminate\Support\Str;
use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
use Dashed\DashedEcommerceCore\Filament\Resources\ProductResource;

class CreateProduct extends CreateRecord
{
    use HasCreatableCMSActions;

    protected static string $resource = ProductResource::class;

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
