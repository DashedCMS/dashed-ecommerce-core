<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Classes\SystemHealth;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\ShippingZone;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedCore\Classes\Launch\LaunchChecklist;

/**
 * De webshoppunten van de livegang-checklist in dashed-core: alles wat er
 * moet staan voordat een klant kan bestellen. Bedrijfsgegevens voor de
 * factuur, een betaalmethode die echt geld ophaalt, een verzendmethode voor
 * elk land waarnaar geleverd wordt, de winkelpagina's, een eerste product,
 * een meldingsadres voor bestellingen, de btw-instelling, en een
 * testbestelling die geplaatst en terugbetaald is. Elk open punt zegt wat er
 * mist en wie het regelt.
 */
class LaunchChecklistItems
{
    public const COMPANY_FIELDS = [
        'company_kvk' => 'KvK-nummer',
        'company_btw' => 'Btw-nummer',
        'company_street' => 'Straat',
        'company_street_number' => 'Huisnummer',
        'company_postal_code' => 'Postcode',
        'company_city' => 'Plaats',
        'company_bank_number' => 'Rekeningnummer',
    ];

    public const SHOP_PAGES = [
        'product_overview_page_id' => 'Productoverzicht',
        'cart_page_id' => 'Winkelwagen',
        'checkout_page_id' => 'Afrekenen',
        'order_page_id' => 'Bestelling (bedankpagina)',
    ];

    public static function register(): void
    {
        if (! class_exists(LaunchChecklist::class)) {
            return;
        }

        LaunchChecklist::extend(fn () => [
            [
                'key' => 'company_details',
                'label' => __('Bedrijfsgegevens voor de factuur'),
                'detail' => __('KvK, btw-nummer, adres en rekeningnummer staan op elke factuur en zijn voor een webshop wettelijk verplicht.'),
                'owner' => __('Een beheerder, in het CMS'),
                'url' => self::url(\Dashed\DashedCore\Filament\Pages\Settings\GeneralSettingsPage::class),
                'check' => fn () => self::missingCompanyFields() === [],
                'problems' => fn () => array_map(fn ($label) => __('Niet ingevuld: :veld', ['veld' => __($label)]), self::missingCompanyFields()),
            ],
            [
                'key' => 'payment_live',
                'label' => __('Betaalmethode live, niet in testmodus'),
                'detail' => __('Een actieve betaalmethode voor de webshop, en geen betaalprovider met een testsleutel op productie (zie Systeemstatus).'),
                'owner' => __('Een beheerder, met de gegevens van de betaalprovider'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Resources\PaymentMethodResource::class),
                'check' => fn () => self::paymentProblems() === [],
                'problems' => fn () => self::paymentProblems(),
            ],
            [
                'key' => 'shipping',
                'label' => __('Verzendmethode voor elk land waarnaar je levert'),
                'detail' => __('De landen in de verzendzones zijn de landen die een klant bij het afrekenen kan kiezen. Een zone zonder verzendmethode betekent dat een klant uit dat land niet kan bestellen.'),
                'owner' => __('Een beheerder, in het CMS'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Resources\ShippingZoneResource::class),
                'check' => fn () => self::shippingProblems() === [],
                'problems' => fn () => self::shippingProblems(),
            ],
            [
                'key' => 'shop_pages',
                'label' => __('Winkelpagina\'s gekoppeld'),
                'detail' => __('Productoverzicht, winkelwagen, afrekenen en de bedankpagina na een bestelling.'),
                'owner' => __('Een beheerder, in het CMS'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Pages\Settings\ProductSettingsPage::class),
                'check' => fn () => self::missingShopPages() === [],
                'problems' => fn () => array_map(fn ($label) => __('Geen pagina gekoppeld voor: :pagina', ['pagina' => __($label)]), self::missingShopPages()),
            ],
            [
                'key' => 'first_product',
                'label' => __('Eerste product online'),
                'detail' => __('Een product dat publiek is en een prijs heeft.'),
                'owner' => __('Een beheerder, in het CMS'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Resources\ProductResource::class),
                'check' => fn () => self::sellableProducts()->exists(),
                'problems' => fn () => [Product::query()->exists()
                    ? __('Er staan producten in het CMS, maar geen enkele is publiek met een prijs.')
                    : __('Er staat nog geen enkel product in het CMS.')],
            ],
            [
                'key' => 'order_notifications',
                'label' => __('Meldingsadres voor nieuwe bestellingen'),
                'detail' => __('Het adres dat een kopie van elke bevestigingsmail krijgt, zodat iemand een nieuwe bestelling ziet binnenkomen.'),
                'owner' => __('Een beheerder, in het CMS'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Pages\Settings\OrderSettingsPage::class),
                'check' => fn () => self::notificationEmails() !== [],
            ],
            [
                'key' => 'vat',
                'label' => __('Btw-instellingen gekozen'),
                'detail' => __('Of prijzen inclusief of exclusief btw zijn ingevoerd.'),
                'owner' => __('Een beheerder, in overleg met de boekhouder'),
                'url' => self::url(\Dashed\DashedEcommerceCore\Filament\Pages\Settings\VATSettingsPage::class),
                'check' => fn () => Customsetting::query()->where('name', 'taxes_prices_include_taxes')->exists(),
            ],
            [
                'key' => 'return_policy',
                'label' => __('Herroepingsrecht en retourvoorwaarden op de site'),
                'detail' => __('Een klant mag binnen 14 dagen herroepen. Zet op de site hoe dat gaat, met een herroepingsformulier en het retouradres.'),
                'owner' => __('Een beheerder, in het CMS'),
                'manual' => true,
            ],
            [
                'key' => 'test_order',
                'label' => __('Testbestelling geplaatst en terugbetaald'),
                'detail' => __('Plaats een echte bestelling met een echte betaling, controleer de mails en de factuur, en betaal hem terug.'),
                'owner' => __('Een beheerder, samen met de ontwikkelaar'),
                'manual' => true,
            ],
        ], 'dashed-ecommerce-core');
    }

    /**
     * @return array<string, string>
     */
    protected static function missingCompanyFields(): array
    {
        $siteId = self::siteId();

        return array_filter(self::COMPANY_FIELDS, fn ($label, $key) => blank(Customsetting::get($key, $siteId)), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<string, string>
     */
    protected static function missingShopPages(): array
    {
        $siteId = self::siteId();

        return array_filter(self::SHOP_PAGES, fn ($label, $key) => blank(Customsetting::get($key, $siteId)), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return array<int, string>
     */
    protected static function paymentProblems(): array
    {
        $problems = [];

        if (! PaymentMethod::query()->where('active', true)->where('type', 'online')->exists()) {
            $problems[] = __('Er staat geen actieve betaalmethode voor de webshop aan.');
        }

        foreach (rescue(fn () => SystemHealth::run(), [], report: false) as $item) {
            if (str_contains($item['key'], '_test_mode_')) {
                $problems[] = $item['label'].(filled($item['detail'] ?? null) ? ': '.$item['detail'] : '');
            }
        }

        return $problems;
    }

    /**
     * @return array<int, string>
     */
    protected static function shippingProblems(): array
    {
        $zones = ShippingZone::query()->withCount('shippingMethods')->get();

        if ($zones->isEmpty()) {
            return [__('Er is nog geen verzendzone, dus een klant kan bij het afrekenen geen land kiezen.')];
        }

        return $zones
            ->filter(fn ($zone) => $zone->shipping_methods_count === 0)
            ->map(fn ($zone) => __('Verzendzone :zone (:landen) heeft geen verzendmethode', [
                'zone' => $zone->name,
                'landen' => implode(', ', (array) $zone->zones) ?: __('geen landen'),
            ]))
            ->values()
            ->all();
    }

    protected static function sellableProducts()
    {
        return Product::query()->publicShowable()->where('current_price', '>', 0);
    }

    /**
     * @return array<int, string>
     */
    protected static function notificationEmails(): array
    {
        $value = Customsetting::get('notification_invoice_emails', self::siteId());
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return array_values(array_filter((array) ($decoded ?? $value), 'filled'));
    }

    protected static function siteId(): ?string
    {
        return Sites::getFirstSite()['id'] ?? null;
    }

    protected static function url(string $class): ?string
    {
        return rescue(fn () => $class::getUrl(), null, report: false);
    }
}
