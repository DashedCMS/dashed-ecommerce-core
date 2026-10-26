<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Classes\SystemHealth;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedCore\Classes\Launch\LaunchChecklist;

/**
 * De webshoppunten van de livegang-checklist in dashed-core: een
 * betaalmethode die echt geld ophaalt, een verzendmethode, de
 * btw-instelling, en een testbestelling die geplaatst en terugbetaald is.
 */
class LaunchChecklistItems
{
    public static function register(): void
    {
        if (! class_exists(LaunchChecklist::class)) {
            return;
        }

        LaunchChecklist::extend(fn () => [
            [
                'key' => 'payment_live',
                'label' => __('Betaalmethode live, niet in testmodus'),
                'detail' => __('Een actieve betaalmethode, en geen betaalprovider met een testsleutel op productie (zie Systeemstatus).'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource::class),
                'check' => fn () => PaymentMethod::query()->where('active', true)->exists()
                    && ! collect(SystemHealth::run())->contains(fn ($item) => str_contains($item['key'], '_test_mode_')),
            ],
            [
                'key' => 'shipping',
                'label' => __('Verzendzone met een verzendmethode'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Resources\ShippingMethodResource::class),
                'check' => fn () => ShippingMethod::query()->whereNotNull('shipping_zone_id')->exists(),
            ],
            [
                'key' => 'vat',
                'label' => __('Btw-instellingen gekozen'),
                'detail' => __('Of prijzen inclusief of exclusief btw zijn ingevoerd.'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage::class),
                'check' => fn () => Customsetting::query()->where('name', 'taxes_prices_include_taxes')->exists(),
            ],
            [
                'key' => 'test_order',
                'label' => __('Testbestelling geplaatst en terugbetaald'),
                'detail' => __('Plaats een echte bestelling met een echte betaling, controleer de mails en de factuur, en betaal hem terug.'),
                'manual' => true,
            ],
        ], 'dashed-ecommerce-core');
    }

    protected static function url(string $class): ?string
    {
        return rescue(fn () => $class::getUrl(), null, report: false);
    }
}
