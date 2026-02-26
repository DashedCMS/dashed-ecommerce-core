<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Dashed\DashedEcommerceCore\Models\Cart;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\CartItem;
use Dashed\DashedEcommerceCore\Models\DiscountCode;

class DatabaseCart
{
    public function __construct(
        protected ?string $type = 'default',
        protected ?int $storeId = null,
    ) {
    }

    public function forType(?string $type): self
    {
        $this->type = $type ?: 'default';

        return $this;
    }

    public function forStore(?int $storeId): self
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function current(): Cart
    {
        return $this->getOrCreateCart();
    }

    public function withItems(array $productRelations = []): Cart
    {
        $cart = $this->getOrCreateCart();

        return $cart->load([
            'items' => function ($q) use ($productRelations) {
                $q->orderByDesc('id')
                    ->with(array_filter([
                        'product' => ! empty($productRelations) ? function ($p) use ($productRelations) {
                            $p->with($productRelations);
                        } : null,
                    ]));
            },
        ]);
    }

    public function addItem(
        ?int $productId,
        int $quantity = 1,
        array $options = [],
        ?string $nameSnapshot = null,
        ?float $unitPriceSnapshot = null,
    ): CartItem {
        $quantity = max(1, (int) $quantity);

        return DB::transaction(function () use ($productId, $quantity, $options, $nameSnapshot, $unitPriceSnapshot) {
            $cart = $this->getOrCreateCart(lockForUpdate: true);

            $options = $this->normalizeOptions($options);
            $hash = $this->optionsHash($options);

            // Merge: zelfde product + zelfde options hash
            $existing = $cart->items()
                ->where('product_id', $productId)
                ->where('options_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->quantity += $quantity;
                $existing->save();

                return $existing->refresh();
            }

            // Snapshot helpers
            if ($productId) {
                $product = Product::find($productId);
                $nameSnapshot ??= $product?->name;
                $unitPriceSnapshot ??= $product ? (float) $product->price : null;
            }

            return $cart->items()->create([
                'product_id' => $productId,
                'name' => $nameSnapshot,
                'unit_price' => $unitPriceSnapshot,
                'quantity' => $quantity,
                'options' => $options,
                'options_hash' => $hash,
            ]);
        });
    }

    public function updateQuantity(int $cartItemId, int $quantity): void
    {
        DB::transaction(function () use ($cartItemId, $quantity) {
            $cart = $this->getOrCreateCart(lockForUpdate: true);

            /** @var CartItem|null $item */
            $item = $cart->items()->whereKey($cartItemId)->lockForUpdate()->first();
            if (! $item) {
                return;
            }

            if ($quantity <= 0) {
                $item->delete();

                return;
            }

            $item->quantity = (int) $quantity;
            $item->save();
        });
    }

    public function removeItem(int $cartItemId): void
    {
        DB::transaction(function () use ($cartItemId) {
            $cart = $this->getOrCreateCart(lockForUpdate: true);
            $cart->items()->whereKey($cartItemId)->delete();
        });
    }

    public function clear(): void
    {
        DB::transaction(function () {
            $cart = $this->getOrCreateCart(lockForUpdate: true);

            $cart->items()->delete();

            // Reset keuzes, zoals jouw emptyCart()
            $cart->discount_code_id = null;
            $cart->shipping_method_id = null;
            $cart->shipping_zone_id = null;
            $cart->payment_method_id = null;
            $cart->deposit_payment_method_id = null;
            $cart->meta = null;
            $cart->save();
        });
    }

    public function setShippingMethod(?int $shippingMethodId, ?int $shippingZoneId = null): void
    {
        $cart = $this->getOrCreateCart();
        $cart->shipping_method_id = $shippingMethodId;
        if (! is_null($shippingZoneId)) {
            $cart->shipping_zone_id = $shippingZoneId;
        }
        $cart->save();
    }

    public function setPaymentMethod(?int $paymentMethodId, ?int $depositPaymentMethodId = null): void
    {
        $cart = $this->getOrCreateCart();
        $cart->payment_method_id = $paymentMethodId;
        if (! is_null($depositPaymentMethodId)) {
            $cart->deposit_payment_method_id = $depositPaymentMethodId;
        }
        $cart->save();
    }

    public function applyDiscountCode(?string $code): array
    {
        $cart = $this->getOrCreateCart();

        if (! $code) {
            $cart->discount_code_id = null;
            $cart->save();

            return ['ok' => true, 'discountCode' => null];
        }

        /** @var DiscountCode|null $discount */
        $discount = DiscountCode::usable()
            ->isNotGlobalDiscount()
            ->where('code', $code)
            ->first();

        if (! $discount || ! $discount->isValidForCart(cartType: $cart->type)) {
            return ['ok' => false, 'message' => 'Discount code not valid'];
        }

        $cart->discount_code_id = $discount->id;
        $cart->save();

        return ['ok' => true, 'discountCode' => $discount->code];
    }

    // ---------------------------
    // Internals
    // ---------------------------

    protected function getOrCreateCart(bool $lockForUpdate = false): Cart
    {
        $userId = auth()->id();
        $token = $this->getOrCreateToken();

        $query = Cart::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        // voorkeur: user cart als ingelogd, anders token cart
        $query->where('type', $this->type ?? 'default');

        if ($this->storeId) {
            $query->where('store_id', $this->storeId);
        } else {
            $query->whereNull('store_id');
        }

        /** @var Cart|null $cart */
        $cart = $userId
            ? (clone $query)->where('user_id', $userId)->first()
            : null;

        if (! $cart) {
            $cart = (clone $query)->where('token', $token)->first();
        }

        if ($cart) {
            // als user net ingelogd is en cart had token: claim hem
            if ($userId && ! $cart->user_id) {
                $cart->user_id = $userId;
                $cart->save();
            }

            return $cart;
        }

        // nieuw
        return Cart::create([
            'user_id' => $userId,
            'token' => $token,
            'type' => $this->type ?? 'default',
            'store_id' => $this->storeId,
            'locale' => app()->getLocale(),
            'currency' => config('app.currency', 'EUR'),
            'meta' => null,
        ]);
    }

    protected function getOrCreateToken(): string
    {
        $cookieName = config('dashed-ecommerce.cart_cookie', 'cart_token');

        $token = request()->cookie($cookieName);
        if ($token && Str::isUuid($token)) {
            return $token;
        }

        $token = (string) Str::uuid();

        // 90 dagen, prima
        Cookie::queue($cookieName, $token, 60 * 24 * 90);

        return $token;
    }

    protected function normalizeOptions(array $options): array
    {
        // remove nulls & sort keys (stable hashing)
        $options = Arr::dot($options);
        $options = array_filter($options, fn ($v) => $v !== null);
        ksort($options);

        // terug naar nested array
        return Arr::undot($options);
    }

    protected function optionsHash(array $options): string
    {
        return hash('sha256', json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
