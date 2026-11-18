<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Closure;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;

/**
 * Uitbreidingspunten van bijbestellen. Een app registreert hier (in een service provider)
 * eigen regels bovenop die van de core; alle registers nemen meerdere callbacks aan.
 */
final class OrderAddOns
{
    /** @var array<int, Closure(Order, string): ?string> */
    private static array $orderRejecters = [];

    /** @var array<int, Closure(Product, Order): ?string> */
    private static array $productExcluders = [];

    /** @var array<int, Closure(Order, int): iterable<Product>> */
    private static array $productSuggesters = [];

    /**
     * Sluit het aanbod voor een order: geef een reden terug om te sluiten, of null.
     * `$context` is `offer` (pagina, bedankblok, checkout) of `mail` (herinneringsmail).
     *
     * @param  Closure(Order $order, string $context): ?string  $callback
     */
    public static function rejectOrderUsing(Closure $callback): void
    {
        self::$orderRejecters[] = $callback;
    }

    /**
     * Sluit een product uit als bijbestelling (voorstellen en wagen): geef een reden terug, of null.
     *
     * @param  Closure(Product $product, Order $parent): ?string  $callback
     */
    public static function excludeProductUsing(Closure $callback): void
    {
        self::$productExcluders[] = $callback;
    }

    /**
     * Extra bron van voorstellen; komt vóór de standaard van de core (CartProductSuggester).
     *
     * @param  Closure(Order $parent, int $limit): iterable<Product>  $callback
     */
    public static function suggestProductsUsing(Closure $callback): void
    {
        self::$productSuggesters[] = $callback;
    }

    /** De eerste reden van een app-callback om het aanbod te sluiten, of null. */
    public static function orderRejection(Order $order, string $context = 'offer'): ?string
    {
        foreach (self::$orderRejecters as $callback) {
            $reason = $callback($order, $context);

            if (filled($reason)) {
                return (string) $reason;
            }
        }

        return null;
    }

    /** De eerste reden van een app-callback om dit product uit te sluiten, of null. */
    public static function productExclusion(Product $product, Order $parent): ?string
    {
        foreach (self::$productExcluders as $callback) {
            $reason = $callback($product, $parent);

            if (filled($reason)) {
                return (string) $reason;
            }
        }

        return null;
    }

    /** @return array<int, Closure(Order, int): iterable<Product>> */
    public static function productSuggesters(): array
    {
        return self::$productSuggesters;
    }

    /** Leegt alle registers (tests). */
    public static function flush(): void
    {
        self::$orderRejecters = [];
        self::$productExcluders = [];
        self::$productSuggesters = [];
    }
}
