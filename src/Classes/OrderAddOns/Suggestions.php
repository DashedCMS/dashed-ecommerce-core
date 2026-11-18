<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Illuminate\Support\Collection;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Services\CartSuggestions\CartProductSuggester;

/**
 * Wat we na een bestelling nog voorstellen om bij te bestellen. Eerst de bronnen van de app
 * (OrderAddOns::suggestProductsUsing()), dan "vaak samen gekocht" bij de order (CartProductSuggester).
 * Nooit iets uit de order zelf of uit dezelfde productgroep, één product per productgroep, geen
 * digitale producten en niets wat reasonToExclude() afwijst.
 */
final class Suggestions
{
    public const DEFAULT_LIMIT = 8;

    public const REASON_NOT_PUBLIC = 'not_public';

    public const REASON_GIFTCARD = 'giftcard';

    public const REASON_NOT_DELIVERABLE = 'not_deliverable';

    public const REASON_ALREADY_IN_ORDER = 'already_in_order';

    /** @return Collection<int, Product> */
    public static function for(Order $order, int $limit = self::DEFAULT_LIMIT): Collection
    {
        if ($limit <= 0) {
            return collect();
        }

        $orderProducts = self::orderProducts($order);
        $productIds = $orderProducts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $groupIds = $orderProducts->pluck('product_group_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();

        $candidates = collect();

        foreach (OrderAddOns::productSuggesters() as $suggester) {
            rescue(function () use (&$candidates, $suggester, $order, $limit) {
                $candidates = $candidates->concat(collect($suggester($order, $limit)));
            }, null, false);
        }

        rescue(function () use (&$candidates, $productIds, $limit) {
            // Een hoog wagenbedrag: geen "gat" naar gratis verzending, dus de gewone samen-gekocht-volgorde.
            $candidates = $candidates->concat(app(CartProductSuggester::class)->suggest(
                cartProductIds: $productIds,
                cartTotal: 100000,
                limit: $limit * 3,
            ));
        }, null, false);

        return $candidates
            ->filter(fn ($product) => $product instanceof Product)
            ->reject(fn (Product $product) => in_array((int) $product->id, $productIds, true)
                || in_array((int) $product->product_group_id, $groupIds, true))
            ->unique(fn (Product $product) => $product->product_group_id ?: 'p'.$product->id)
            ->reject(fn (Product $product) => $product->isDigital())
            ->filter(fn (Product $product) => self::exclusionReason($product, $order, $productIds) === null)
            ->take($limit)
            ->values();
    }

    /**
     * Waarom dit product niet als bijbestelling bij deze order kan, of null als het past. Geldt voor de
     * voorstellen en voor wat de klant zelf in de wagen legt.
     */
    public static function reasonToExclude(Product $product, Order $parent): ?string
    {
        $productIds = self::orderProducts($parent)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return self::exclusionReason($product, $parent, $productIds);
    }

    /** @param  array<int, int>  $orderProductIds */
    private static function exclusionReason(Product $product, Order $parent, array $orderProductIds): ?string
    {
        if (! $product->public || ($parent->site_id && ! in_array($parent->site_id, (array) $product->site_ids, true))) {
            return self::REASON_NOT_PUBLIC;
        }

        if ($product->isGiftcardProduct()) {
            return self::REASON_GIFTCARD;
        }

        if (! $product->isDigital() && ! $product->inStock()) {
            return self::REASON_NOT_DELIVERABLE;
        }

        if (in_array((int) $product->id, $orderProductIds, true)) {
            return self::REASON_ALREADY_IN_ORDER;
        }

        return OrderAddOns::productExclusion($product, $parent);
    }

    /** @return Collection<int, Product> */
    private static function orderProducts(Order $order): Collection
    {
        return $order->orderProducts()
            ->whereNotNull('product_id')
            ->with('product.productGroup')
            ->get()
            ->pluck('product')
            ->filter()
            ->values();
    }
}
