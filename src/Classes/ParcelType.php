<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Classes;

use WeakMap;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Models\Order;

/**
 * Carrier-agnostic "verzendvorm" of an order: is it a normal parcel, a
 * letterbox parcel, a pickup, a digital order, ...
 *
 * Carrier package-type codes are identical for Veloyd and MyParcel
 * (1 Pakket, 2 Brievenbus, 3 Brief, 4 Stamp, 5 Pallet, 6 Klein pakket).
 * Pickup, digital and unknown have no carrier code.
 *
 * Resolution order in forOrder():
 *  a. shipping method sort `take_away`            -> pickup
 *  b. all product lines are digital               -> digital
 *  c. app overrides (overrideUsing), first non-null wins
 *  d. the latest stored non-return label row (veloydOrders / myParcelOrders,
 *     only when the carrier package registered that relation) with a package_type
 *  e. carrier computations (computeUsing), first non-null wins
 *  f. unknown
 */
class ParcelType
{
    public const PACKAGE = 'package';

    public const MAILBOX = 'mailbox';

    public const LETTER = 'letter';

    public const STAMP = 'stamp';

    public const PALLET = 'pallet';

    public const SMALL_PACKAGE = 'small_package';

    public const PICKUP = 'pickup';

    public const DIGITAL = 'digital';

    public const UNKNOWN = 'unknown';

    /** Default stored label relations that carrier packages add to Order via addDynamicRelation(). */
    public const LABEL_RELATIONS = ['veloydOrders', 'myParcelOrders'];

    /**
     * key => [code, label, color, hex, icon]
     *
     * @var array<string, array{code: ?int, label: string, color: string, hex: string, icon: string}>
     */
    private const TYPES = [
        self::PACKAGE => ['code' => 1, 'label' => 'PAKKET', 'color' => 'blue', 'hex' => '#1d4ed8', 'icon' => 'heroicon-o-cube'],
        self::MAILBOX => ['code' => 2, 'label' => 'BRIEVENBUS', 'color' => 'orange', 'hex' => '#c2410c', 'icon' => 'heroicon-o-inbox'],
        self::LETTER => ['code' => 3, 'label' => 'BRIEF', 'color' => 'yellow', 'hex' => '#a16207', 'icon' => 'heroicon-o-envelope'],
        self::STAMP => ['code' => 4, 'label' => 'STAMP', 'color' => 'teal', 'hex' => '#0f766e', 'icon' => 'heroicon-o-ticket'],
        self::PALLET => ['code' => 5, 'label' => 'PALLET', 'color' => 'stone', 'hex' => '#57534e', 'icon' => 'heroicon-o-truck'],
        self::SMALL_PACKAGE => ['code' => 6, 'label' => 'KLEIN PAKKET', 'color' => 'cyan', 'hex' => '#0e7490', 'icon' => 'heroicon-o-archive-box'],
        self::PICKUP => ['code' => null, 'label' => 'OPHALEN', 'color' => 'gray', 'hex' => '#4b5563', 'icon' => 'heroicon-o-building-storefront'],
        self::DIGITAL => ['code' => null, 'label' => 'DIGITAAL', 'color' => 'purple', 'hex' => '#7e22ce', 'icon' => 'heroicon-o-cloud-arrow-down'],
        self::UNKNOWN => ['code' => null, 'label' => 'ONBEKEND', 'color' => 'red', 'hex' => '#b91c1c', 'icon' => 'heroicon-o-question-mark-circle'],
    ];

    /** @var array<int, string> */
    private static array $labelRelations = self::LABEL_RELATIONS;

    /** @var array<int, callable> */
    private static array $overrides = [];

    /** @var array<int, callable> */
    private static array $computations = [];

    /** Memo per Order instance: never outlives the model, so queue workers cannot serve stale results. */
    private static ?WeakMap $memo = null;

    /**
     * App-level override, `fn(Order $order): int|string|null` (carrier code or key).
     * Checked after pickup/digital, before stored labels. First non-null wins.
     */
    public static function overrideUsing(callable $fn): void
    {
        self::$overrides[] = $fn;
        self::flush();
    }

    /**
     * Carrier fallback computation, `fn(Order $order): ?int` (carrier code).
     * Only used when no override and no stored label decided. First non-null wins.
     */
    public static function computeUsing(callable $fn): void
    {
        self::$computations[] = $fn;
        self::flush();
    }

    /**
     * @return array{key: string, code: ?int, label: string, color: string}
     */
    public static function forOrder(Order $order): array
    {
        self::$memo ??= new WeakMap();

        if (isset(self::$memo[$order])) {
            return self::$memo[$order];
        }

        return self::$memo[$order] = self::describe(self::resolveKey($order));
    }

    /** The carrier code to use for a label (null for pickup, digital and unknown). */
    public static function carrierCodeFor(Order $order): ?int
    {
        return self::forOrder($order)['code'];
    }

    /**
     * @return array{key: string, code: ?int, label: string, color: string}
     */
    public static function describe(string $key): array
    {
        $type = self::TYPES[$key] ?? self::TYPES[self::UNKNOWN];
        $key = isset(self::TYPES[$key]) ? $key : self::UNKNOWN;

        return ['key' => $key, 'code' => $type['code'], 'label' => $type['label'], 'color' => $type['color']];
    }

    /** Key for a carrier code or key (int, numeric string or key string); null when not recognised. */
    public static function keyFor(int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || ctype_digit((string) $value)) {
            foreach (self::TYPES as $key => $type) {
                if ($type['code'] === (int) $value) {
                    return $key;
                }
            }

            return null;
        }

        return isset(self::TYPES[$value]) ? (string) $value : null;
    }

    /** @return array<string, string> key => uppercase Dutch label */
    public static function labels(): array
    {
        return array_map(fn (array $type): string => $type['label'], self::TYPES);
    }

    public static function label(string $key): string
    {
        return self::describe($key)['label'];
    }

    /** Hex colour for custom (non-Filament) markup such as the order page banner. */
    public static function hex(string $key): string
    {
        return (self::TYPES[$key] ?? self::TYPES[self::UNKNOWN])['hex'];
    }

    /** Heroicon name for this type (box for parcels, inbox for letterbox, ...). */
    public static function icon(string $key): string
    {
        return (self::TYPES[$key] ?? self::TYPES[self::UNKNOWN])['icon'];
    }

    /**
     * Filament colour palette for badges, so no panel needs to register custom colour names.
     *
     * @return array<int|string, string>
     */
    public static function filamentColor(string $key): array
    {
        return match (self::describe($key)['color']) {
            'blue' => Color::Blue,
            'orange' => Color::Orange,
            'yellow' => Color::Yellow,
            'teal' => Color::Teal,
            'stone' => Color::Stone,
            'cyan' => Color::Cyan,
            'gray' => Color::Gray,
            'purple' => Color::Purple,
            default => Color::Red,
        };
    }

    /**
     * Relations to eager-load on order lists so forOrder() needs no extra queries
     * (apart from what compute callables do themselves).
     *
     * @return array<int, string>
     */
    public static function eagerLoads(): array
    {
        $relations = ['shippingMethod', 'orderProducts.product.productGroup'];

        foreach (self::$labelRelations as $relation) {
            if (Order::hasDynamicRelation($relation)) {
                $relations[] = $relation;
            }
        }

        return $relations;
    }

    /**
     * Replace the Order relations that hold stored label rows (a carrier with
     * its own relation, or tests). Only relations Order really has are used.
     *
     * @param  array<int, string>  $relations
     */
    public static function useLabelRelations(array $relations): void
    {
        self::$labelRelations = array_values($relations);
        self::flush();
    }

    /** Forget memoised results (e.g. after a label was created). */
    public static function flush(): void
    {
        self::$memo = null;
    }

    /** Remove all registered callables, custom label relations and memoised results (tests). */
    public static function reset(): void
    {
        self::$overrides = [];
        self::$computations = [];
        self::$labelRelations = self::LABEL_RELATIONS;
        self::flush();
    }

    private static function resolveKey(Order $order): string
    {
        if ($order->shippingMethod?->sort === 'take_away') {
            return self::PICKUP;
        }

        if (self::isDigitalOnly($order)) {
            return self::DIGITAL;
        }

        foreach (self::$overrides as $fn) {
            if ($key = self::keyFor($fn($order))) {
                return $key;
            }
        }

        if ($key = self::keyFor(self::storedPackageType($order))) {
            return $key;
        }

        foreach (self::$computations as $fn) {
            if ($key = self::keyFor($fn($order))) {
                return $key;
            }
        }

        return self::UNKNOWN;
    }

    /** True when the order has product lines and every one of them is digital. */
    private static function isDigitalOnly(Order $order): bool
    {
        $products = $order->orderProducts
            ->filter(fn ($line) => $line->product_id)
            ->map(fn ($line) => $line->product)
            ->filter();

        return $products->isNotEmpty()
            && $products->every(fn ($product) => $product->isDigital());
    }

    /** package_type of the newest non-return label row over all carrier relations. */
    private static function storedPackageType(Order $order): int|string|null
    {
        $latest = null;

        foreach (self::$labelRelations as $relation) {
            if (! Order::hasDynamicRelation($relation)) {
                continue;
            }

            $row = $order->relationLoaded($relation)
                ? $order->getRelation($relation)
                    ->filter(fn ($label) => ! $label->is_return && filled($label->package_type))
                    ->sortByDesc(fn ($label) => [$label->created_at?->getTimestamp() ?? 0, $label->getKey()])
                    ->first()
                : $order->{$relation}()
                    ->where(fn ($q) => $q->where('is_return', false)->orWhereNull('is_return'))
                    ->whereNotNull('package_type')
                    ->where('package_type', '!=', '')
                    ->latest('created_at')
                    ->latest('id')
                    ->first();

            if ($row instanceof Model && (! $latest || $row->created_at > $latest->created_at)) {
                $latest = $row;
            }
        }

        return $latest?->package_type;
    }
}
