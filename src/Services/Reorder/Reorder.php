<?php

namespace Dashed\DashedEcommerceCore\Services\Reorder;

use Illuminate\Support\Facades\URL;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductExtraOption;

/**
 * "Bestel opnieuw": zet de producten van een eerdere bestelling terug in de
 * winkelwagen, met dezelfde aantallen en extra's, voor zover die nog bestaan
 * en verkoopbaar zijn. Wat niet meer kan komt terug in het resultaat, zodat
 * de klant ziet wat er ontbreekt in plaats van een stille halve winkelwagen.
 *
 * Bewust geen eigen prijzen: de winkelwagen rekent zelf opnieuw met de
 * prijzen van nu (Product::getShoppingCartItemPrice()).
 */
class Reorder
{
    public const SETTING = 'reorder_enabled';

    /**
     * Herkomsten waarvan wij de klant zijn. Een marktplaats (Bol, Etsy)
     * praat zelf met zijn klant; die krijgt van ons geen herhaallink.
     */
    public const ALLOWED_ORIGINS = ['own', 'pos'];

    public const SKIP_UNAVAILABLE = 'unavailable';
    public const SKIP_OUT_OF_STOCK = 'out_of_stock';
    public const SKIP_NEEDS_CHOICE = 'needs_choice';

    public static function enabled(?string $siteId = null): bool
    {
        return (bool) Customsetting::get(self::SETTING, $siteId ?: Sites::getActive(), false);
    }

    public static function allowedFor(Order $order): bool
    {
        if (! self::enabled($order->site_id)) {
            return false;
        }

        $origin = (string) ($order->order_origin ?: 'own');

        if (! in_array($origin, self::ALLOWED_ORIGINS, true)) {
            return false;
        }

        // Een creditorder is een tegenboeking, geen bestelling om te herhalen.
        if ($order->credit_for_order_id) {
            return false;
        }

        return in_array($order->status, ['paid', 'waiting_for_confirmation', 'partially_paid', 'return'], true);
    }

    /**
     * De ondertekende link voor mail en account, of een lege string als
     * deze bestelling niet opnieuw besteld kan worden. Zonder vervaldatum:
     * een bevestigingsmail wordt soms een jaar later nog geopend.
     */
    public static function url(Order $order): string
    {
        if (! $order->hash || ! self::allowedFor($order)) {
            return '';
        }

        return URL::signedRoute('dashed.frontend.reorder', ['orderHash' => $order->hash]);
    }

    public static function fill(Order $order): ReorderResult
    {
        $result = new ReorderResult();

        if (! self::allowedFor($order)) {
            return $result;
        }

        $lines = $order->orderProducts()->with('product')->get();

        // Onderdelen van een bundel staan als losse regels van 0 euro op de
        // order. De bundel zelf voegt ze weer toe; los nog eens zou dubbel zijn.
        $bundleChildIds = $lines
            ->filter(fn (OrderProduct $line) => $line->product?->is_bundle)
            ->flatMap(fn (OrderProduct $line) => $line->product->bundleProducts->pluck('id'))
            ->unique()
            ->all();

        foreach ($lines as $line) {
            if (in_array($line->sku, ['shipping_costs', 'payment_costs'], true)) {
                continue;
            }

            if ($line->product_id && in_array($line->product_id, $bundleChildIds, true) && (float) $line->price == 0.0) {
                continue;
            }

            $name = (string) $line->name;
            $product = $line->product_id
                ? Product::query()->publicShowable()->whereKey($line->product_id)->first()
                : null;

            if (! $product) {
                $result->skip($name, self::SKIP_UNAVAILABLE);

                continue;
            }

            if (! $product->inStock()) {
                $result->skip($name, self::SKIP_OUT_OF_STOCK);

                continue;
            }

            [$options, $droppedExtras, $missingRequired] = self::extrasFor($product, (array) ($line->product_extras ?? []));

            if ($missingRequired) {
                $result->skip($name, self::SKIP_NEEDS_CHOICE, $product->getUrl());

                continue;
            }

            $quantity = max(1, (int) $line->quantity);

            if ($product->limit_purchases_per_customer && $product->limit_purchases_per_customer_limit) {
                $quantity = min($quantity, (int) $product->limit_purchases_per_customer_limit);
            }

            $attributes = ['options' => $options];

            if (is_array($line->hidden_options) && $line->hidden_options !== []) {
                $attributes['hiddenOptions'] = $line->hidden_options;
            }

            cartHelper()->addToCart($product->id, $quantity, $attributes);

            $result->added($name, $quantity, $droppedExtras);
        }

        return $result;
    }

    /**
     * Bouwt de opties van de winkelwagen terug uit de product_extras van de
     * orderregel. Een extra of optie die niet meer bij het product hoort valt
     * weg (en wordt gemeld); mist er daardoor een verplichte keuze, dan moet de
     * klant naar de productpagina.
     *
     * @return array{0: array<string|int, array>, 1: array<int, string>, 2: bool}
     */
    protected static function extrasFor(Product $product, array $productExtras): array
    {
        $available = $product->allProductExtras() ?? collect();
        $availableExtraIds = $available->pluck('id')->map(fn ($id) => (int) $id)->all();
        $availableOptionIds = $available
            ->flatMap(fn ($extra) => $extra->productExtraOptions->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $options = [];
        $dropped = [];
        $chosenExtraIds = [];

        foreach ($productExtras as $extra) {
            if (! is_array($extra) || ! isset($extra['id'])) {
                continue;
            }

            $key = (string) $extra['id'];
            $label = trim(($extra['name'] ?? '') . ': ' . ($extra['value'] ?? ''), ': ');

            if (str_contains($key, 'product-extra-')) {
                $extraId = (int) str($key)->explode('-')->last();

                // Een geüpload bestand alleen terugzetten als het pad in onze
                // eigen map staat; zie ProductExtraFile::resolve().
                $isFile = str_contains($key, 'product-extra-file-');
                $path = (string) ($extra['path'] ?? '');

                if (! in_array($extraId, $availableExtraIds, true) || ($isFile && ! str_starts_with($path, 'dashed/product-extras/'))) {
                    $dropped[] = $label;

                    continue;
                }

                $options[$key] = array_filter([
                    'name' => $extra['name'] ?? '',
                    'value' => $extra['value'] ?? '',
                    'path' => $isFile ? $path : null,
                ], fn ($value) => $value !== null);
                $chosenExtraIds[] = $extraId;

                continue;
            }

            $optionId = (int) $key;

            if ($optionId <= 0 || ! in_array($optionId, $availableOptionIds, true)) {
                $dropped[] = $label;

                continue;
            }

            $option = ProductExtraOption::find($optionId);

            $options[$optionId] = [
                'name' => $extra['name'] ?? ($option?->productExtra?->name ?? ''),
                'value' => $option?->value ?? ($extra['value'] ?? ''),
            ];
            $chosenExtraIds[] = (int) $option?->product_extra_id;
        }

        $missingRequired = $available
            ->filter(fn ($extra) => (bool) $extra->required)
            ->contains(fn ($extra) => ! in_array((int) $extra->id, $chosenExtraIds, true));

        return [$options, $dropped, $missingRequired];
    }
}
