<?php

namespace Dashed\DashedEcommerceCore\Classes\OrderAddOns;

use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;

/**
 * Add-on mode: the visitor orders something extra for an earlier, paid order (the "parent"),
 * shipped in the same box. The signed offer link puts the parent id in the session. While the
 * offer is open and nothing in the cart is excluded, the checkout locks the address to the
 * parent's, offers only the free shipping method and links the new order to the parent.
 */
final class CheckoutMode
{
    public const SESSION_KEY = 'order_add_on_parent_id';

    /** Customer-facing fields of the checkout that are locked to the parent: property => order column. */
    public const FIXED_FIELDS = [
        'email' => 'email',
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'street' => 'street',
        'houseNr' => 'house_nr',
        'zipCode' => 'zip_code',
        'city' => 'city',
        'country' => 'country',
        'phoneNumber' => 'phone_number',
        'company' => 'company_name',
    ];

    public const REASON_CLOSED = 'closed';

    public const REASON_EXCLUDED_PRODUCT = 'excluded_product';

    /** @var array<string, array{parent: ?Order, active: bool, reason: ?string, product: ?string}> */
    private static array $states = [];

    private static bool $resolverRegistered = false;

    public static function start(Order $order): void
    {
        session()->put(self::SESSION_KEY, $order->id);
        self::reset();
    }

    public static function stop(): void
    {
        if (self::hasSession()) {
            session()->forget(self::SESSION_KEY);
        }

        self::reset();
    }

    /** Forget the per-request memo (after a status change or a cart change within one request). */
    public static function reset(): void
    {
        self::$states = [];
    }

    /** The parent id from the session, also when the offer has closed meanwhile. */
    public static function requestedParentId(): ?int
    {
        if (! self::hasSession()) {
            return null;
        }

        $id = (int) session()->get(self::SESSION_KEY);

        return $id > 0 ? $id : null;
    }

    /** The parent order, only while its offer is open. */
    public static function parent(): ?Order
    {
        return self::state()['parent'];
    }

    /** Offer open and nothing in the cart is excluded: fixed address, free shipping, linked order. */
    public static function active(): bool
    {
        return self::state()['active'];
    }

    /** Was the visitor in add-on mode while the offer has closed meanwhile? */
    public static function closed(): bool
    {
        return self::requestedParentId() !== null && self::state()['reason'] === self::REASON_CLOSED;
    }

    /**
     * @return array{parent: ?Order, active: bool, reason: ?string, product: ?string}
     *                                                                                reason: `closed` (offer closed) or `excluded_product` (then `product` holds its name)
     */
    public static function state(): array
    {
        $empty = ['parent' => null, 'active' => false, 'reason' => null, 'product' => null];
        $parentId = self::requestedParentId();

        if (! $parentId) {
            return $empty;
        }

        $products = self::cartProducts();
        $key = $parentId . '|' . spl_object_id(request()) . '|' . implode(',', array_map(fn (Product $product) => $product->id, $products));

        if (isset(self::$states[$key])) {
            return self::$states[$key];
        }

        $parent = Order::find($parentId);

        if (! $parent || ! rescue(fn () => Offer::isOpen($parent), false, true)) {
            return self::$states[$key] = array_merge($empty, ['reason' => self::REASON_CLOSED]);
        }

        $state = ['parent' => $parent, 'active' => true, 'reason' => null, 'product' => null];

        foreach ($products as $product) {
            if (rescue(fn () => OrderAddOns::productExclusion($product, $parent), null, true)) {
                $state['active'] = false;
                $state['reason'] = self::REASON_EXCLUDED_PRODUCT;
                $state['product'] = (string) $product->name;

                break;
            }
        }

        return self::$states[$key] = $state;
    }

    /**
     * The values of the fixed checkout fields from the parent, or [] outside an active mode.
     *
     * @return array<string, string>
     */
    public static function fixedValues(): array
    {
        if (! self::active()) {
            return [];
        }

        $parent = self::parent();
        $values = [];

        foreach (self::FIXED_FIELDS as $property => $column) {
            $values[$property] = (string) ($parent->{$column} ?? '');
        }

        return $values;
    }

    /** Free for the customer and not a pickup: the only kind of shipping method offered in add-on mode. */
    public static function isFreeShippingMethod(mixed $method): bool
    {
        if (! $method) {
            return false;
        }

        $sort = data_get($method, 'sort');

        return $sort !== 'take_away' && ($sort === 'free_delivery' || (float) data_get($method, 'costs', 0) <= 0.0);
    }

    /**
     * For the shipping-value resolver chain: in add-on mode the cart counts as at least the free-shipping
     * threshold of the parent's zone, so the free shipping method becomes available.
     */
    public static function shippingOrderValue(float $total): float
    {
        if (! self::requestedParentId() || ! self::active()) {
            return $total;
        }

        $method = Offer::freeShippingMethod(self::parent());

        return $method ? max($total, (float) $method->minimum_order_value) : $total;
    }

    /** Idempotent: statics survive repeated app boots within one PHP process (tests, Octane). */
    public static function registerShippingValueResolver(): void
    {
        if (self::$resolverRegistered) {
            return;
        }

        self::$resolverRegistered = true;

        ShoppingCart::addShippingOrderValueResolver(
            fn ($total) => rescue(fn () => self::shippingOrderValue((float) $total), (float) $total, true)
        );
    }

    /** @return array<int, Product> */
    private static function cartProducts(): array
    {
        $products = [];

        foreach (rescue(fn () => cartHelper()->getCartItems(), [], false) ?? [] as $item) {
            $model = $item->model ?? null;

            if ($model instanceof Product) {
                $products[$model->id] = $model;
            }
        }

        ksort($products);

        return array_values($products);
    }

    private static function hasSession(): bool
    {
        // Not request()->hasSession(): Livewire sometimes renders outside a normal request. Without a
        // started session (queue, console) there simply is no mode.
        return (bool) rescue(fn () => app()->bound('session') && session()->has(self::SESSION_KEY), false, false);
    }
}
