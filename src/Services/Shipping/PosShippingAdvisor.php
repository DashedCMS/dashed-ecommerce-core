<?php

namespace Dashed\DashedEcommerceCore\Services\Shipping;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\POSCart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\Countries;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

/**
 * Verzendadvies voor de kassa, met dezelfde regels als de webshop.
 *
 * Wat geldig is bepaalt ShoppingCart::getAvailableShippingMethods(), met de
 * regels van de POSCart in plaats van de sessiewagen. De kassa toont daarna
 * toch elke methode: een kassamedewerker mag een uitzondering maken, dus een
 * methode die niet past blijft kiesbaar, met de reden erbij. De regel over
 * de betaalmethode doet niet mee, want die wordt in de kassa pas na de
 * verzending gekozen.
 */
class PosShippingAdvisor
{
    /**
     * @return array<int, array{id: int, name: string, fullName: string, costs: float, costsFormatted: string, valid: bool, recommended: bool, reason: ?string}>
     */
    public static function advise(POSCart $posCart, float $orderValue): array
    {
        $country = self::country($posCart);
        $items = self::items($posCart);
        $zone = ShoppingCart::getShippingZoneByCountry($country);

        $valid = collect(ShoppingCart::getAvailableShippingMethods($country, self::address($posCart), null, $orderValue, $items))
            ->keyBy('id');

        $recommendedId = $valid->sortBy([['costs', 'asc'], ['order', 'asc']])->first()?->id;

        $productIds = $items->map(fn ($item) => $item->model?->id)->filter()->values()->all();
        $productGroupIds = $items->map(fn ($item) => $item->model?->product_group_id)->filter()->values()->all();

        $advice = ShippingMethod::with('shippingZone')
            ->orderBy('order')
            ->get()
            ->map(function (ShippingMethod $shippingMethod) use ($valid, $recommendedId, $zone, $items, $orderValue, $productIds, $productGroupIds) {
                $isValid = $valid->has($shippingMethod->id);
                $costs = (float) ($isValid
                    ? $valid->get($shippingMethod->id)->costs
                    : $shippingMethod->costsForCart($shippingMethod->shipping_zone_id, $items));
                $name = $shippingMethod->getTranslation('name', app()->getLocale());

                return [
                    'id' => $shippingMethod->id,
                    'name' => $name,
                    'fullName' => self::label($shippingMethod, $name, $costs),
                    'costs' => round($costs, 2),
                    'costsFormatted' => $costs > 0 ? CurrencyHelper::formatPrice($costs) : __('gratis'),
                    'valid' => $isValid,
                    'recommended' => $shippingMethod->id === $recommendedId,
                    'reason' => $isValid ? null : self::reason($shippingMethod, $zone?->id, $orderValue, $productIds, $productGroupIds),
                ];
            });

        return $advice->sortBy(fn (array $row) => $row['valid'] ? 0 : 1)->values()->all();
    }

    /**
     * Verzendkosten van één methode over de regels in de kassa, voor de zone
     * van de klant. Dezelfde som als in advise(), zodat wat de kassa toont en
     * wat er op de bestelling komt niet uiteenlopen.
     */
    public static function costsFor(POSCart $posCart, ShippingMethod $shippingMethod): float
    {
        $zone = ShoppingCart::getShippingZoneByCountry(self::country($posCart));

        return (float) $shippingMethod->costsForCart($zone?->id, self::items($posCart));
    }

    /**
     * Het land van de klant, anders het eerste land van de winkel; dezelfde
     * terugval als bij het afrekenen in de kassa.
     */
    public static function country(POSCart $posCart): string
    {
        return (string) ($posCart->country ?: (Countries::getAllSelectedCountries()[0] ?? ''));
    }

    /**
     * De regels van de kassa in de vorm die de verzendberekening leest.
     *
     * @return Collection<int, object{model: ?Product, qty: int}>
     */
    public static function items(POSCart $posCart): Collection
    {
        $lines = collect($posCart->products ?? [])
            ->filter(fn ($line) => (int) ($line['quantity'] ?? 0) > 0);

        $productIds = $lines->pluck('id')->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->all();
        $products = Product::with('shippingClasses')->whereIn('id', $productIds)->get()->keyBy('id');

        return $lines->map(fn ($line) => (object) [
            'model' => is_numeric($line['id'] ?? null) ? $products->get((int) $line['id']) : null,
            'qty' => (int) $line['quantity'],
        ])->values();
    }

    private static function address(POSCart $posCart): string
    {
        if (! $posCart->street || ! $posCart->city) {
            return '';
        }

        return trim("{$posCart->street} {$posCart->house_nr}, {$posCart->zip_code} {$posCart->city}, {$posCart->country}");
    }

    private static function label(ShippingMethod $shippingMethod, string $name, float $costs): string
    {
        $zone = $shippingMethod->shippingZone;

        if ($zone && count($zone->zones ?? []) > 1) {
            $name .= ' (' . implode(', ', $zone->zones) . ')';
        } elseif ($zone) {
            $name .= ' (' . $zone->name . ')';
        }

        return $name . ' ' . ($costs > 0 ? CurrencyHelper::formatPrice($costs) : __('gratis'));
    }

    /**
     * Waarom een methode niet past, in de volgorde waarin de webshop toetst.
     *
     * @param  array<int, int>  $productIds
     * @param  array<int, int>  $productGroupIds
     */
    private static function reason(ShippingMethod $shippingMethod, ?int $zoneId, float $orderValue, array $productIds, array $productGroupIds): string
    {
        if (! $zoneId || (int) $shippingMethod->shipping_zone_id !== $zoneId) {
            return __('Andere verzendzone');
        }

        if ($orderValue < (float) $shippingMethod->minimum_order_value) {
            return __('Vanaf :bedrag', ['bedrag' => CurrencyHelper::formatPrice($shippingMethod->minimum_order_value)]);
        }

        if ($orderValue > (float) $shippingMethod->maximum_order_value) {
            return __('Tot :bedrag', ['bedrag' => CurrencyHelper::formatPrice($shippingMethod->maximum_order_value)]);
        }

        if (($productIds && $shippingMethod->disabledProducts()->whereIn('product_id', $productIds)->exists())
            || ($productGroupIds && $shippingMethod->disabledProductGroups()->whereIn('product_group_id', $productGroupIds)->exists())) {
            return __('Niet voor een product in de kassa');
        }

        return __('Past niet bij de webshopregels');
    }
}
