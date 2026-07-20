<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedEcommerceCore\Filament\Resources\AutomationRuleResource;

class CreateAutomationRule extends CreateRecord
{
    protected static string $resource = AutomationRuleResource::class;

    /**
     * Zelfde patroon als PaymentMethodResource/ShippingZoneResource: het
     * site_id-veld is verborgen (en dus niet ingevuld) zodra er maar 1 site
     * is, dus altijd expliciet terugvallen op de eerste site.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? Sites::getFirstSite()['id'];

        return $data;
    }
}
