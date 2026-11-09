<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets;

use Filament\Widgets\Widget;
use Dashed\DashedEcommerceCore\Models\MetaCapiEventCount;
use Dashed\DashedEcommerceCore\Services\Meta\MetaCapiSettings;

/**
 * Dagtellers van de browser-events (ViewContent, AddToCart, InitiateCheckout)
 * boven het Meta CAPI-log. Alleen op die pagina, niet op het dashboard.
 */
class MetaCapiEventCountsWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'dashed-ecommerce-core::filament.widgets.meta-capi-event-counts';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'eventNames' => array_keys(MetaCapiSettings::BROWSER_EVENTS),
            'days' => MetaCapiEventCount::overview(7),
        ];
    }
}
