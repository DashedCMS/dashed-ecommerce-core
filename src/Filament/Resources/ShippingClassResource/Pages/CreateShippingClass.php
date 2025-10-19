<?php

namespace Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
<<<<<<< HEAD
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
=======
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;
>>>>>>> fb4555ce42557585ae0976d428f4262d50f93752
use Dashed\DashedEcommerceCore\Filament\Resources\ShippingClassResource;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateShippingClass extends CreateRecord
{
    use Translatable;

    protected static string $resource = ShippingClassResource::class;

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        foreach (ShippingZone::all() as $shippingZone) {
            $data['price_shipping_zones'][$shippingZone->id] = $data["price_shipping_zone_{$shippingZone->id}"] ?? null;
            unset($data["price_shipping_zone_{$shippingZone->id}"]);
        }

        return $data;
    }
}
