<?php

namespace Dashed\DashedEcommerceCore\Classes;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedTranslations\Models\Translation;
use Dashed\DashedEcommerceCore\Models\DiscountCode;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;
use Dashed\DashedEcommerceCore\Models\ShippingMethod;
use Dashed\DashedEcommerceCore\Models\Cart as CartModel;
use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;
use Dashed\DashedEcommerceCore\Models\CartItem as CartItemModel;

class CartHelper
{
    public static float $total = 0.0;
    public static float $totalWithoutDiscount = 0.0;
    public static float $subtotal = 0.0;
    public static float $discount = 0.0;
    public static float $tax = 0.0;
    public static float $taxWithoutDiscount = 0.0;
    public static float $shippingCosts = 0.0;
    public static float $paymentCosts = 0.0;
    public static float $depositAmount = 0.0;
    public static bool $isPostPayMethod = false;

    public static ?array $totalAmountForVats = [];
    public static ?array $totalVatPerPercentage = [];
    public static ?array $vatPercentageOfTotals = [];
    public static int $vatRates = 0;
    public static array $taxPercentages = [];
    public static int $vatRatesCount = 0;

    public static ?DiscountCode $discountCode = null;
    public static ?string $discountCodeString = null;
    public static bool $calculateInclusiveTax = false;

    public static ?int $shippingMethod = null;
    public static null|array|Collection $allPaymentMethods = [];
    public static ?int $paymentMethod = null;
    public static ?int $shippingZone = null;
    public static null|array|\Illuminate\Database\Eloquent\Collection $depositPaymentMethods = null;
    public static ?int $depositPaymentMethod = null;

    public static ?string $cartType = 'default';
    public static null|array|Collection $cartItems = [];
    public static array $cartProductsById = [];
    public static bool $initialized = false;

    /** @var CartModel|null */
    public static ?CartModel $cart = null;

    // Flags om te weten wat al berekend is
    public static bool $cartItemsInitialized = false;
    public static bool $discountCodeInitialized = false;
    public static bool $vatBaseInitialized = false;
    public static bool $taxInitialized = false;
    public static bool $subtotalInitialized = false;
    public static bool $totalInitialized = false;
    public static bool $totalWithoutDiscountInitialized = false;
    public static bool $discountInitialized = false;
    public static bool $shippingCostsInitialized = false;
    public static bool $paymentCostsInitialized = false;
    public static bool $depositPaymentMethodsInitialized = false;
    public static bool $depositAmountInitialized = false;
    public static bool $taxPercentagesInitialized = false;
    public static bool $allPaymentMethodsInitialized = false;

    /**
     * Reset alle computed flags zodat we weer vers kunnen rekenen.
     */
    protected function resetComputedFlags(): void
    {
        static::$cartItemsInitialized = false;
        static::$discountCodeInitialized = false;
        static::$vatBaseInitialized = false;
        static::$taxInitialized = false;
        static::$subtotalInitialized = false;
        static::$totalInitialized = false;
        static::$totalWithoutDiscountInitialized = false;
        static::$discountInitialized = false;
        static::$shippingCostsInitialized = false;
        static::$paymentCostsInitialized = false;
        static::$depositPaymentMethodsInitialized = false;
        static::$depositAmountInitialized = false;
        static::$taxPercentagesInitialized = false;
    }

    public function __construct()
    {
        static::$calculateInclusiveTax = Customsetting::get('taxes_prices_include_taxes') ? true : false;
    }

    /**
     * Initialiseert de cart helper voor de huidige request.
     * updateData() wordt ALTIJD uitgevoerd.
     */
    public function initialize(?string $cartType = null): void
    {
        $this->setCartType($cartType);

        if (static::isInitialized()) {
            return;
        }

        static::$initialized = true;

        $this->updateData();
    }

    /**
     * Volledige herberekening van alle bedragen.
     * Deze functie OVERRIDET ALLES (force = true).
     */
    public function updateData(): void
    {
        $this->setCartType();

        // Eerst alle flags resetten
        $this->resetComputedFlags();

        // Zorg dat we cart loaded hebben
        $this->getOrCreateCart();

        // Sync runtime vars uit DB cart
        static::$shippingMethod = static::$cart?->shipping_method_id;
        static::$shippingZone = static::$cart?->shipping_zone_id;
        static::$paymentMethod = static::$cart?->payment_method_id;
        static::$depositPaymentMethod = static::$cart?->deposit_payment_method_id;

        // Alles opnieuw berekenen, geforceerd
        $this->setCartItems(true);
        $this->setDiscountCode(false, true);
        $this->setVatBaseInfoForCalculation(true);
        $this->setTax(true);
        $this->setShippingCosts(true);
        $this->setPaymentMethodCosts(true);
        $this->setTotal(true);
        $this->setTotalWithoutDiscount(true);
        $this->setDiscount(true);
        $this->setSubtotal(true);
        $this->setTaxPercentages(true);
    }

    // -------------------------------------------------------------------------
    // Cart fetch / create (DB)
    // -------------------------------------------------------------------------

    protected function getCookieName(): string
    {
        return config('dashed-ecommerce.cart_cookie', 'cart_token');
    }

    protected function getOrCreateToken(): string
    {
        $cookieName = $this->getCookieName();

        $token = request()->cookie($cookieName);
        if ($token && Str::isUuid($token)) {
            return $token;
        }

        $token = (string) Str::uuid();

        // 90 dagen is prima
        Cookie::queue($cookieName, $token, 60 * 24 * 90);

        return $token;
    }

    protected function getOrCreateCart(bool $lockForUpdate = false): CartModel
    {
        if (static::$cart && ! $lockForUpdate) {
            return static::$cart;
        }

        $token = $this->getOrCreateToken();
        $userId = auth()->id();

        $query = CartModel::query()->where('type', static::$cartType ?? 'default');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        // Eerst user-cart (als ingelogd)
        $cart = null;
        if ($userId) {
            $cart = (clone $query)->where('user_id', $userId)->first();
        }

        // Anders token-cart (guest)
        if (! $cart) {
            $cart = (clone $query)->where('token', $token)->first();
        }

        if (! $cart) {
            $cart = CartModel::create([
                'user_id' => $userId,
                'token' => $token,
                'type' => static::$cartType ?? 'default',
                'locale' => app()->getLocale(),
                'currency' => config('app.currency', 'EUR'),
            ]);
        } else {
            // claim cart bij login
            if ($userId && ! $cart->user_id) {
                $cart->user_id = $userId;
                $cart->save();
            }
        }

        static::$cart = $cart;

        return $cart;
    }

    // -------------------------------------------------------------------------
    // Cart items (DB -> "cart item object" shape)
    // -------------------------------------------------------------------------

    /**
     * We bouwen een lightweight object dat lijkt op Gloudemans cart item:
     * - rowId
     * - id (product_id)
     * - qty
     * - price (unit price)
     * - options (array)
     * - model (Product|null)
     *
     * Zo blijven Product::getShoppingCartItemPrice(s) calls werken zonder grote refactor.
     */
    protected function mapDbCartItemToRuntime(CartItemModel $item): object
    {
        $o = new \stdClass();

        $o->rowId = (string) $item->id;
        $o->id = $item->product_id; // gloudemans: id = product_id
        $o->qty = (int) $item->quantity;
        $o->price = (float) ($item->unit_price ?? 0.0);

        $options = $item->options ?? [];
        if (! is_array($options)) {
            $options = (array) $options;
        }

        // Zorg dat opties altijd array zijn
        $o->options = $options;

        // model vullen later via preload
        $o->model = null;

        // Voor compat: sommige code checkt associatedModel
        $o->associatedModel = null;

        return $o;
    }

    private function setCartItems(bool $force = false): void
    {
        if (static::$cartItemsInitialized && ! $force) {
            return;
        }

        $cart = $this->getOrCreateCart();

        // Items ophalen
        $items = CartItemModel::query()
            ->where('cart_id', $cart->id)
            ->orderByDesc('id')
            ->get();

        // Map naar runtime objects
        static::$cartItems = $items->map(fn (CartItemModel $i) => $this->mapDbCartItemToRuntime($i));
        static::$cartItemsInitialized = true;

        static::$cartProductsById = [];
        $this->preloadCartProducts();
    }

    public static function preloadProductsForCartItems($cartItems, array $relations = []): \Illuminate\Support\Collection
    {
        $ids = collect($cartItems)
            ->pluck('id') // product_id
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = Product::query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        return $query->whereIn('id', $ids)->get()->keyBy('id');
    }

    public function preloadCartProducts(array $relations = []): void
    {
        if (! empty(static::$cartProductsById)) {
            return;
        }

        $this->setCartItems(); // zorgt dat static::$cartItems gevuld is

        $ids = collect(static::$cartItems)
            ->pluck('id') // id = product_id
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            static::$cartProductsById = [];

            return;
        }

        $query = Product::query();

        if (! empty($relations)) {
            $query->with($relations);
        }

        $products = $query
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        static::$cartProductsById = $products->all();

        // runtime items model koppelen
        static::$cartItems = collect(static::$cartItems)->map(function ($cartItem) {
            $product = static::$cartProductsById[$cartItem->id] ?? null;
            if ($product) {
                $cartItem->model = $product;
                $cartItem->associatedModel = $product;
            }

            return $cartItem;
        });
    }

    public function getProductForCartItem($cartItem): ?Product
    {
        $id = $cartItem->id; // product_id

        return static::$cartProductsById[$id] ?? null;
    }

    // -------------------------------------------------------------------------
    // Payment methods + Discount code (DB)
    // -------------------------------------------------------------------------

    private function setAllPaymentMethods(bool $force = false): void
    {
        if (static::$allPaymentMethodsInitialized && ! $force) {
            return;
        }

        static::$allPaymentMethods = ShoppingCart::getPaymentMethods(skipTotalCheck: true);
        static::$allPaymentMethodsInitialized = true;
    }

    private function setDiscountCode(bool $forceString = false, bool $force = false): void
    {
        if (static::$discountCodeInitialized && ! $force) {
            return;
        }

        $cart = $this->getOrCreateCart();

        // code komt uit DB (cart->discount_code_id), maar we supporten nog forceString voor apply flow
        $code = $forceString ? static::$discountCodeString : null;

        if (! $code && $cart->discount_code_id) {
            $discount = DiscountCode::find($cart->discount_code_id);
            if ($discount) {
                static::$discountCode = $discount;
                static::$discountCodeString = $discount->code ?? null;
                static::$discountCodeInitialized = true;

                return;
            }
        }

        if (! $code) {
            static::$discountCode = null;
            static::$discountCodeString = null;

            // ensure db cleared
            if ($cart->discount_code_id) {
                $cart->discount_code_id = null;
                $cart->save();
            }

            static::$discountCodeInitialized = true;

            return;
        }

        $discountCode = DiscountCode::usable()
            ->isNotGlobalDiscount()
            ->where('code', $code)
            ->first();

        if (! $discountCode || ! $discountCode->isValidForCart(cartType: static::$cartType)) {
            static::$discountCode = null;
            static::$discountCodeString = null;

            $cart->discount_code_id = null;
            $cart->save();
        } else {
            static::$discountCode = $discountCode;
            static::$discountCodeString = $discountCode->code ?? null;

            $cart->discount_code_id = $discountCode->id;
            $cart->save();
        }

        static::$discountCodeInitialized = true;
    }

    // -------------------------------------------------------------------------
    // VAT / TAX / totals (jouw bestaande logic blijft mostly intact)
    // -------------------------------------------------------------------------

    private function setVatBaseInfoForCalculation(bool $force = false): void
    {
        if (static::$vatBaseInitialized && ! $force) {
            return;
        }

        $this->setCartItems(); // zeker zijn dat cartItems gezet is
        $this->preloadCartProducts(['bundleProducts']);

        $tax = 0;
        $taxWithoutDiscount = 0;
        $vatRates = 0;
        $vatRatesCount = 0;
        $totalAmountForVats = [];
        $totalPriceForProducts = 0;

        foreach (static::$cartItems as $cartItem) {
            $product = $this->getProductForCartItem($cartItem);

            if ($product || ($cartItem->options['customProduct'] ?? false)) {
                $cartProducts = [$product ?? $cartItem];

                if ($product && $product->is_bundle && $product->use_bundle_product_price) {
                    $cartProducts = $product->bundleProducts;
                }

                foreach ($cartProducts as $cartProduct) {
                    $originalModel = $cartItem->model;
                    $cartItem->model = $cartProduct;
                    $prices = Product::getShoppingCartItemPrices($cartItem, static::$discountCode);
                    $cartItem->model = $originalModel;

                    $price = $prices['with_discount'];
                    $priceWithoutDiscount = $prices['without_discount'];

                    $totalPriceForProducts += $price;

                    $vatRate = $cartProduct->options['vat_rate'] ?? $cartProduct->vat_rate;

                    if (static::$calculateInclusiveTax) {
                        $price = $price / (100 + $vatRate) * $vatRate;
                        $priceWithoutDiscount = $priceWithoutDiscount / (100 + $vatRate) * $vatRate;
                    } else {
                        $price = $price / 100 * $vatRate;
                        $priceWithoutDiscount = $priceWithoutDiscount / 100 * $vatRate;
                    }

                    $tax += $price;
                    $taxWithoutDiscount += $priceWithoutDiscount;

                    if ($vatRate > 0) {
                        $index = number_format($vatRate, 0);

                        if (! isset($totalAmountForVats[$index])) {
                            $totalAmountForVats[$index] = 0;
                        }

                        if (static::$discountCode && static::$discountCode->type == 'percentage') {
                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem, static::$discountCode);
                            if (! static::$calculateInclusiveTax) {
                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + $vatRate);
                            }

                            $totalAmountForVats[$index] += $totalCartItemAmount;
                        } else {
                            $totalCartItemAmount = Product::getShoppingCartItemPrice($cartItem);
                            if (! static::$calculateInclusiveTax) {
                                $totalCartItemAmount = $totalCartItemAmount / 100 * (100 + $vatRate);
                            }

                            $totalAmountForVats[$index] += $totalCartItemAmount;
                        }

                        $vatRates += $vatRate * $cartItem->qty;
                        $vatRatesCount += $cartItem->qty;
                    }
                }
            }
        }

        $vatPercentageOfTotals = [];
        $totalVatPerPercentage = [];

        foreach ($totalAmountForVats as $percentage => $totalAmountForVat) {
            $percentageKey = number_format($percentage, 0);

            if (! isset($vatPercentageOfTotals[$percentageKey])) {
                $vatPercentageOfTotals[$percentageKey] = 0;
            }
            if (! isset($totalVatPerPercentage[$percentageKey])) {
                $totalVatPerPercentage[$percentageKey] = 0;
            }

            $vatPercentageOfTotals[$percentageKey] += $totalAmountForVat > 0.00 && $totalPriceForProducts > 0.00
                ? ($totalAmountForVat / $totalPriceForProducts) * 100
                : 0;

            $totalVatPerPercentage[$percentageKey] += $totalAmountForVat > 0.00
                ? ($totalAmountForVat / (100 + $percentage) * $percentage)
                : 0;
        }

        static::$totalAmountForVats = $totalAmountForVats;
        static::$totalVatPerPercentage = $totalVatPerPercentage;
        static::$vatPercentageOfTotals = $vatPercentageOfTotals;
        static::$vatRates = $vatRates;
        static::$vatRatesCount = $vatRatesCount;
        static::$tax = $tax;
        static::$taxWithoutDiscount = $taxWithoutDiscount;

        static::$vatBaseInitialized = true;
    }

    public function getVatRateForShippingMethod(): int
    {
        if (static::$shippingMethod && static::$vatRatesCount) {
            return (int) round(static::$vatRates / static::$vatRatesCount, 2);
        }

        return 0;
    }

    public function getVatForShippingMethod(?int $vatRate = null): float
    {
        if (! static::$shippingMethod) {
            return 0.0;
        }

        if (! $vatRate) {
            $vatRate = $this->getVatRateForShippingMethod();
        }

        $shippingMethod = ShippingMethod::find(static::$shippingMethod);
        if (! $shippingMethod) {
            return 0.0;
        }

        $baseCosts = $shippingMethod->costsForCart(static::$shippingZone);
        $tax = 0;

        if (static::$calculateInclusiveTax) {
            $tax = $baseCosts / (100 + $vatRate) * $vatRate;
        } else {
            $tax = $baseCosts / 100 * $vatRate;
        }

        return round($tax, 2);
    }

    public function getVatForPaymentMethod(): float
    {
        $tax = 0;

        if (static::$paymentMethod) {
            foreach (self::getAllPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod && $paymentMethod['extra_costs'] > 0) {
                    if (static::$calculateInclusiveTax) {
                        $tax += ($paymentMethod['extra_costs'] / 121 * 21);
                    } else {
                        $tax += ($paymentMethod['extra_costs'] / 100 * 21);
                    }
                }
            }
        }

        return round($tax, 2);
    }

    private function setTax(bool $force = false): void
    {
        if (static::$taxInitialized && ! $force) {
            return;
        }

        $this->setVatBaseInfoForCalculation();

        $tax = static::$tax;
        $taxWithoutDiscount = static::$taxWithoutDiscount;

        if (static::$discountCode && static::$discountCode->type == 'amount') {
            $discountAmount = static::$discountCode->discount_amount;

            if (static::$calculateInclusiveTax) {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $tax -= (($discountAmount * ($vatPercentageOfTotal / 100)) / (100 + $percentage) * $percentage);
                    }
                }
            } else {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        $tax -= (($discountAmount * ($vatPercentageOfTotal / 100)) / 100 * $percentage);
                    }
                }
            }
        }

        $shippingVat = $this->getVatForShippingMethod();
        $paymentVat = $this->getVatForPaymentMethod();

        $tax += $shippingVat + $paymentVat;
        $taxWithoutDiscount += $shippingVat + $paymentVat;

        if ($tax < 0) {
            $tax = 0;
        }
        if ($taxWithoutDiscount < 0) {
            $taxWithoutDiscount = 0;
        }

        static::$tax = (float) number_format($tax, 2, '.', '');
        static::$taxWithoutDiscount = (float) number_format($taxWithoutDiscount, 2, '.', '');

        static::$taxInitialized = true;
    }

    private function setSubtotal(bool $force = false): void
    {
        if (static::$subtotalInitialized && ! $force) {
            return;
        }

        $total = static::$totalWithoutDiscount;

        if (! static::$calculateInclusiveTax) {
            $total -= static::$taxWithoutDiscount;

            if (static::$shippingMethod) {
                $shippingMethod = ShippingMethod::find(static::$shippingMethod);
                $total -= $shippingMethod ? $shippingMethod->costsForCart(static::$shippingZone) : 0;
            }

            if (static::$paymentMethod) {
                foreach (self::getAllPaymentMethods() as $paymentMethod) {
                    if ($paymentMethod['id'] == static::$paymentMethod) {
                        $total -= $paymentMethod['extra_costs'];
                    }
                }
            }
        }

        if ($total < 0) {
            $total = 0.01;
        }

        static::$subtotal = (float) number_format($total, 2, '.', '');
        static::$subtotalInitialized = true;
    }

    private function setTotalWithoutDiscount(bool $force = false): void
    {
        if (static::$totalWithoutDiscountInitialized && ! $force) {
            return;
        }

        static::$totalWithoutDiscount = static::$total;
        static::$totalWithoutDiscountInitialized = true;
    }

    private function setShippingCosts(bool $force = false): void
    {
        if (static::$shippingCostsInitialized && ! $force) {
            return;
        }

        $shippingCosts = 0.0;

        if (static::$shippingMethod) {
            $shippingMethod = ShippingMethod::find(static::$shippingMethod);
            $shippingCosts = $shippingMethod ? $shippingMethod->costsForCart(static::$shippingZone) : 0.0;
        }

        static::$shippingCosts = $shippingCosts;
        static::$shippingCostsInitialized = true;
    }

    private function setPaymentMethodCosts(bool $force = false): void
    {
        if (static::$paymentCostsInitialized && ! $force) {
            return;
        }

        $paymentCosts = 0.0;
        $isPostPayMethod = false;

        if (static::$paymentMethod) {
            foreach (self::getAllPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod) {
                    $paymentCosts = $paymentMethod['extra_costs'];
                    $isPostPayMethod = $paymentMethod['postpay'];
                }
            }
        }

        static::$paymentCosts = $paymentCosts;
        static::$isPostPayMethod = $isPostPayMethod;
        static::$paymentCostsInitialized = true;
    }

    public function getIsPostpayPaymentMethod(): bool
    {
        return static::$isPostPayMethod;
    }

    public function setTotal(bool $force = false): void
    {
        if (static::$totalInitialized && ! $force) {
            return;
        }

        $this->setCartItems();
        $this->preloadCartProducts();

        $total = 0.0;

        foreach (static::$cartItems as $cartItem) {
            $product = $this->getProductForCartItem($cartItem);

            if ($product) {
                $cartItem->model = $product;
                $total += Product::getShoppingCartItemPrice($cartItem);
            } else {
                $total += ((float) $cartItem->price) * ((int) $cartItem->qty);
            }
        }

        if (! static::$calculateInclusiveTax) {
            $this->setTax();
            $total += static::$tax;
        }

        if (static::$shippingMethod) {
            $shippingMethod = ShippingMethod::find(static::$shippingMethod);
            $total += $shippingMethod ? $shippingMethod->costsForCart(static::$shippingZone) : 0;
        }

        if (static::$paymentMethod) {
            foreach (self::getAllPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod) {
                    $total += $paymentMethod['extra_costs'];
                }
            }
        }

        if ($total < 0) {
            $total = 0.01;
        }

        static::$total = (float) $total;
        static::$totalInitialized = true;
    }

    private function setDiscount(bool $force = false): void
    {
        if (static::$discountInitialized && ! $force) {
            return;
        }

        $totalDiscount = 0.0;

        if (static::$discountCode) {
            if (static::$discountCode->type == 'percentage') {
                $this->setCartItems();
                $itemsInCart = static::$cartItems;

                foreach ($itemsInCart as $item) {
                    $totalDiscount += Product::getShoppingCartItemPrice($item)
                        - Product::getShoppingCartItemPrice($item, static::$discountCode);
                }
            } elseif (static::$discountCode->type == 'amount') {
                $totalDiscount = static::$discountCode->discount_amount;
            }
        }

        $total = static::$total;

        if ($totalDiscount > $total) {
            $totalDiscount = $total - 0.01;
        }

        static::$discount = (float) number_format($totalDiscount, 2, '.', '');
        static::$total = (float) number_format(static::$totalWithoutDiscount - $totalDiscount, 2, '.', '');

        static::$discountInitialized = true;
    }

    public function setShippingMethod(?int $shippingMethod = null): void
    {
        static::$shippingMethod = $shippingMethod;

        $cart = $this->getOrCreateCart();
        $cart->shipping_method_id = $shippingMethod;
        $cart->save();

        static::$shippingCostsInitialized = false;
        static::$taxInitialized = false;
        static::$taxPercentagesInitialized = false;
        static::$totalInitialized = false;
        static::$subtotalInitialized = false;
        static::$totalWithoutDiscountInitialized = false;
    }

    public function setShippingZone(?int $shippingZone = null): void
    {
        static::$shippingZone = $shippingZone;

        $cart = $this->getOrCreateCart();
        $cart->shipping_zone_id = $shippingZone;
        $cart->save();

        static::$shippingCostsInitialized = false;
        static::$taxInitialized = false;
        static::$taxPercentagesInitialized = false;
        static::$totalInitialized = false;
        static::$subtotalInitialized = false;
        static::$totalWithoutDiscountInitialized = false;
    }

    public function setPaymentMethod(?int $paymentMethod = null): void
    {
        static::$paymentMethod = $paymentMethod;

        $cart = $this->getOrCreateCart();
        $cart->payment_method_id = $paymentMethod;
        $cart->save();

        static::$paymentCostsInitialized = false;
        static::$isPostPayMethod = false;
        static::$depositPaymentMethodsInitialized = false;
        static::$depositAmountInitialized = false;
        static::$taxInitialized = false;
        static::$taxPercentagesInitialized = false;
        static::$totalInitialized = false;
        static::$subtotalInitialized = false;
        static::$totalWithoutDiscountInitialized = false;

        $this->setDepositPaymentMethods(true);
    }

    public function setDepositPaymentMethod(?int $depositPaymentMethod = null): void
    {
        static::$depositPaymentMethod = $depositPaymentMethod;

        $cart = $this->getOrCreateCart();
        $cart->deposit_payment_method_id = $depositPaymentMethod;
        $cart->save();

        static::$depositAmountInitialized = false;
    }

    public function setDepositPaymentMethods(bool $force = false): void
    {
        if (static::$depositPaymentMethodsInitialized && ! $force) {
            return;
        }

        $paymentMethod = PaymentMethod::find(static::$paymentMethod);
        $depositPaymentMethods = [];

        if ($paymentMethod && $paymentMethod->deposit_calculation_payment_method_ids) {
            $depositPaymentMethods = PaymentMethod::find($paymentMethod->deposit_calculation_payment_method_ids)->toArray();

            foreach ($depositPaymentMethods as &$depositPaymentMethod) {
                $depositPaymentMethod['full_image_path'] = $depositPaymentMethod['image']
                    ? Storage::disk('dashed')->url($depositPaymentMethod['image'])
                    : '';
                $depositPaymentMethod['name'] = $depositPaymentMethod['name'][app()->getLocale()] ?? '';
                $depositPaymentMethod['additional_info'] = $depositPaymentMethod['additional_info'][app()->getLocale()] ?? '';
                $depositPaymentMethod['payment_instructions'] = $depositPaymentMethod['payment_instructions'][app()->getLocale()] ?? '';
            }
        }

        static::$depositPaymentMethods = $depositPaymentMethods;
        static::$depositPaymentMethodsInitialized = true;

        $this->setDepositAmount(true);
    }

    public function setDepositAmount(bool $force = false): void
    {
        if (static::$depositAmountInitialized && ! $force) {
            return;
        }

        $depositAmount = 0.0;

        if (static::$paymentMethod) {
            foreach (self::getAllPaymentMethods() as $paymentMethod) {
                if ($paymentMethod['id'] == static::$paymentMethod) {
                    if ($paymentMethod['deposit_calculation']) {
                        $formula = str_replace('{ORDER_TOTAL_MINUS_PAYMENT_COSTS}', static::$total, $paymentMethod['deposit_calculation']);
                        $formula = str_replace('{ORDER_TOTAL}', static::$total, $formula);
                        $depositAmount = eval('return ' . $formula . ';');
                    }
                }
            }
        }

        static::$depositAmount = (float) number_format($depositAmount, 2);
        static::$depositAmountInitialized = true;
    }

    private function setTaxPercentages(bool $force = false): void
    {
        if (static::$taxPercentagesInitialized && ! $force) {
            return;
        }

        $totalVatPerPercentage = static::$totalVatPerPercentage;

        if (static::$discountCode && static::$discountCode->type == 'amount') {
            $discount = static::$discountCode->discount_amount;

            if (static::$calculateInclusiveTax) {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        foreach ($totalVatPerPercentage as $p => $value) {
                            $totalVatPerPercentage[$p] -= (($discount * ($vatPercentageOfTotal / 100)) / (100 + $p) * $p);
                        }
                    }
                }
            } else {
                foreach (static::$vatPercentageOfTotals as $percentage => $vatPercentageOfTotal) {
                    if ($vatPercentageOfTotal) {
                        foreach ($totalVatPerPercentage as $p => $value) {
                            $totalVatPerPercentage[$p] -= (($discount * ($vatPercentageOfTotal / 100)) / 100 * $p);
                        }
                    }
                }
            }
        }

        if (static::$shippingMethod) {
            foreach ($totalVatPerPercentage as $percentage => $value) {
                $result = $this->getVatForShippingMethod((int) $percentage);
                $totalVatPerPercentage[$percentage] += $result;
            }
        }

        if (static::$paymentMethod) {
            $paymentVat = $this->getVatForPaymentMethod();
            $totalVatPerPercentage[21] = ($totalVatPerPercentage[21] ?? 0) + $paymentVat;
        }

        foreach ($totalVatPerPercentage as $percentage => $value) {
            $totalVatPerPercentage[$percentage] = round($value, 2);
        }

        static::$taxPercentages = $totalVatPerPercentage;
        static::$taxPercentagesInitialized = true;
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getAllPaymentMethods(): null|array|Collection
    {
        if (! static::$allPaymentMethodsInitialized) {
            $this->setAllPaymentMethods();
        }

        return static::$allPaymentMethods;
    }

    public function getTotal(): float
    {
        if (! static::$totalInitialized) {
            $this->setTotal();
        }

        return static::$total;
    }

    public function getTaxPercentages(): array
    {
        if (! static::$taxPercentagesInitialized) {
            $this->setTaxPercentages();
        }

        return static::$taxPercentages;
    }

    public function getDiscount(): float
    {
        if (! static::$discountInitialized) {
            $this->setDiscount();
        }

        return static::$discount;
    }

    public function getTax(): float
    {
        if (! static::$taxInitialized) {
            $this->setTax();
        }

        return static::$tax;
    }

    public function getTotalWithoutDiscount(): float
    {
        if (! static::$totalWithoutDiscountInitialized) {
            $this->setTotalWithoutDiscount();
        }

        return static::$totalWithoutDiscount;
    }

    public function getSubtotal(): float
    {
        if (! static::$subtotalInitialized) {
            $this->setSubtotal();
        }

        return static::$subtotal;
    }

    public function getShippingCosts(): float
    {
        if (! static::$shippingCostsInitialized) {
            $this->setShippingCosts();
        }

        return static::$shippingCosts;
    }

    public function getPaymentCosts(): float
    {
        if (! static::$paymentCostsInitialized) {
            $this->setPaymentMethodCosts();
        }

        return static::$paymentCosts;
    }

    public function getDepositAmount(): float
    {
        if (! static::$depositAmountInitialized) {
            $this->setDepositAmount();
        }

        return static::$depositAmount;
    }

    public function getDepositPaymentMethods(): null|array|\Illuminate\Database\Eloquent\Collection
    {
        if (! static::$depositPaymentMethodsInitialized) {
            $this->setDepositPaymentMethods();
        }

        return static::$depositPaymentMethods;
    }

    public function getDepositPaymentMethod(): ?PaymentMethod
    {
        if (! static::$depositPaymentMethod) {
            return null;
        }

        return PaymentMethod::find(static::$depositPaymentMethod);
    }

    public function getDiscountCode(): ?DiscountCode
    {
        if (! static::$discountCodeInitialized) {
            $this->setDiscountCode();
        }

        return static::$discountCode;
    }

    public function getDiscountCodeString(): ?string
    {
        if (! static::$discountCodeInitialized) {
            $this->setDiscountCode();
        }

        return static::$discountCodeString;
    }

    public function getCartItems(): Collection|null|array
    {
        if (! static::$cartItemsInitialized) {
            $this->setCartItems();
        }

        return static::$cartItems;
    }

    // -------------------------------------------------------------------------
    // Mutators (DB)
    // -------------------------------------------------------------------------

    /**
     * Add item (merge by product_id + options hash)
     */
    public function addToCart(string|int $productId, int $quantity = 1, array $options = []): array
    {
        $quantity = max(1, (int) $quantity);

        DB::transaction(function () use ($productId, $quantity, $options) {
            $cart = $this->getOrCreateCart(lockForUpdate: true);

            $options = $this->normalizeOptions($options);
            $hash = $this->optionsHash($options);

            $existing = CartItemModel::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $productId)
                ->where('options_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->quantity += $quantity;
                $existing->save();
            } else {
                $product = Product::find($productId);

                CartItemModel::create([
                    'cart_id' => $cart->id,
                    'product_id' => $productId,
                    'name' => $product?->name,
                    'unit_price' => $product ? (float) $product->price : 0.0,
                    'quantity' => $quantity,
                    'options' => $options,
                    'options_hash' => $hash,
                ]);
            }
        });

        $this->updateData();

        return [
            'status' => 'success',
            'message' => Translation::get('product-added-to-cart', static::$cartType, 'The product has been added to your cart'),
        ];
    }

    /**
     * Update qty by cart_item_id (voorheen rowId)
     */
    public function changeQuantity(string $rowId, int $quantity): array
    {
        $dispatch = [];

        $cart = $this->getOrCreateCart();
        $itemId = (int) $rowId;

        $cartItem = CartItemModel::query()
            ->where('cart_id', $cart->id)
            ->where('id', $itemId)
            ->first();

        if (! $cartItem) {
            $this->updateData();

            return [
                'status' => 'success',
                'message' => Translation::get('product-updated-to-cart', static::$cartType, 'The product has been updated to your cart'),
                'dispatch' => $dispatch,
            ];
        }

        if ($quantity <= 0) {
            $product = $cartItem->product_id ? Product::find($cartItem->product_id) : null;

            EcommerceActionLog::createLog('remove_from_cart', $cartItem->quantity, productId: $product?->id);

            $cartItem->delete();

            $this->updateData();

            $cartTotal = static::$total;

            $dispatch = [
                'event' => 'productRemovedFromCart',
                'data' => [
                    'product' => $product,
                    'productName' => $product?->name,
                    'quantity' => $quantity,
                    'price' => $product ? number_format((float) $product->price, 2, '.', '') : '0.00',
                    'cartTotal' => number_format($cartTotal, 2, '.', ''),
                    'category' => $product?->productCategories?->first()?->name ?? null,
                    'tiktokItems' => TikTokHelper::getShoppingCartItems($cartTotal) ?? [],
                ],
            ];

            return [
                'status' => 'success',
                'message' => Translation::get('product-removed-from-cart', static::$cartType, 'The product has been removed from your cart'),
                'dispatch' => $dispatch,
            ];
        }

        // logging add/remove delta
        $product = $cartItem->product_id ? Product::find($cartItem->product_id) : null;

        if ($cartItem->quantity > $quantity) {
            EcommerceActionLog::createLog('remove_from_cart', ($cartItem->quantity - $quantity), productId: $product?->id);
        } else {
            EcommerceActionLog::createLog('add_to_cart', ($quantity - $cartItem->quantity), productId: $product?->id);
        }

        $cartItem->quantity = (int) $quantity;
        $cartItem->save();

        $this->updateData();

        return [
            'status' => 'success',
            'message' => Translation::get('product-updated-to-cart', static::$cartType, 'The product has been updated to your cart'),
            'dispatch' => $dispatch,
        ];
    }

    public function removeItem(string $rowId): void
    {
        $this->changeQuantity($rowId, 0);
    }

    public function emptyCart(): void
    {
        $cart = $this->getOrCreateCart();

        DB::transaction(function () use ($cart) {
            CartItemModel::query()->where('cart_id', $cart->id)->delete();

            $cart->discount_code_id = null;
            $cart->shipping_method_id = null;
            $cart->shipping_zone_id = null;
            $cart->payment_method_id = null;
            $cart->deposit_payment_method_id = null;
            $cart->meta = null;
            $cart->save();
        });

        $this->resetComputedFlags();
        static::$cartItems = [];
        static::$total = 0.0;
        static::$totalWithoutDiscount = 0.0;
        static::$subtotal = 0.0;
        static::$discount = 0.0;
        static::$tax = 0.0;
        static::$taxWithoutDiscount = 0.0;
        static::$shippingCosts = 0.0;
        static::$paymentCosts = 0.0;
        static::$depositAmount = 0.0;
        static::$discountCode = null;
        static::$discountCodeString = null;
        static::$discountCodeInitialized = false;
        static::$shippingMethod = null;
        static::$paymentMethod = null;
        static::$depositPaymentMethod = null;

        // Refresh cart pointer
        static::$cart = null;
    }

    public function applyDiscountCode(?string $code = null): array
    {
        $cart = $this->getOrCreateCart();

        if (! $code) {
            static::$discountCodeString = null;
            static::$discountCode = null;

            $cart->discount_code_id = null;
            $cart->save();

            $this->updateData();

            return [
                'status' => 'danger',
                'message' => Translation::get('discount-code-not-valid', static::$cartType, 'The discount code is not valid'),
            ];
        }

        static::$discountCodeString = $code;
        $this->setDiscountCode(true, true);
        $this->updateData();

        if (! static::$discountCode) {
            return [
                'status' => 'danger',
                'message' => Translation::get('discount-code-not-valid', static::$cartType, 'The discount code is not valid'),
            ];
        }

        return [
            'status' => 'success',
            'message' => Translation::get('discount-code-applied', static::$cartType, 'The discount code has been applied and discount has been calculated'),
        ];
    }

    /**
     * Dit blijft inhoudelijk hetzelfde, maar nu DB removals/updates.
     */
    public function removeInvalidItems($checkStock = true): bool
    {
        $cartChanged = false;

        $this->setCartItems(true);
        $this->preloadCartProducts(['productGroup', 'volumeDiscounts', 'productCategories']);

        $cart = $this->getOrCreateCart();
        $cartItems = collect(static::$cartItems);
        $parentItemsToCheck = collect();

        foreach ($cartItems as $runtimeItem) {
            $itemId = (int) $runtimeItem->rowId;

            /** @var CartItemModel|null $dbItem */
            $dbItem = CartItemModel::query()
                ->where('cart_id', $cart->id)
                ->where('id', $itemId)
                ->first();

            if (! $dbItem) {
                continue;
            }

            $model = $this->getProductForCartItem($runtimeItem);

            if (! $model && ! ($runtimeItem->options['customProduct'] ?? false)) {
                $dbItem->delete();
                $cartChanged = true;

                continue;
            }

            // Product verwijderd of niet meer toonbaar
            if ($model && ($model->trashed() || ! $model->publicShowable())) {
                $dbItem->delete();
                EcommerceActionLog::createLog('remove_from_cart', $runtimeItem->qty, productId: $model->id);

                Notification::make()
                    ->body(Translation::get('product-removed', 'cart', ':product: is uit je winkelwagen gehaald omdat het product niet meer beschikbaar is.', 'text', [
                        'product' => $model->name,
                    ]))
                    ->danger()
                    ->send();

                $cartChanged = true;

                continue;
            }

            // Stock checks
            if ($checkStock && $model && $model->stock() < $runtimeItem->qty) {
                $newStock = $model->stock();
                if ($newStock > 0) {
                    $dbItem->quantity = $newStock;
                    $dbItem->save();

                    EcommerceActionLog::createLog('remove_from_cart', $runtimeItem->qty - $newStock, productId: $model->id);

                    Notification::make()
                        ->body(Translation::get('product-less-stock', 'cart', ':product: is verlaagd in je winkelwagen omdat er maar :stock: voorraad is.', 'text', [
                            'product' => $model->name,
                            'stock' => $newStock,
                        ]))
                        ->danger()
                        ->send();

                    $cartChanged = true;
                } else {
                    EcommerceActionLog::createLog('remove_from_cart', $runtimeItem->qty, productId: $model->id);
                    $dbItem->delete();

                    Notification::make()
                        ->body(Translation::get('product-out-of-stock', 'cart', ':product: is uit je winkelwagen gehaald omdat er geen voorraad meer is.', 'text', [
                            'product' => $model->name,
                        ]))
                        ->danger()
                        ->send();

                    $cartChanged = true;
                }
            }

            // Aankooplimieten per klant
            if ($model && $model->limit_purchases_per_customer && $runtimeItem->qty > $model->limit_purchases_per_customer_limit) {
                EcommerceActionLog::createLog('remove_from_cart', $runtimeItem->qty - $model->limit_purchases_per_customer_limit, productId: $model->id);

                $dbItem->quantity = $model->limit_purchases_per_customer_limit;
                $dbItem->save();

                $cartChanged = true;
            }

            // Parent product group stock
            if ($model && $model->productGroup && ($model->productGroup->use_parent_stock ?? false)) {
                $parentItemsToCheck->push($model->productGroup->id);
            }

            // Volume korting & prijs sync (unit_price snapshot)
            if ($model) {
                $price = $runtimeItem->options['originalPrice'] ?? null;

                if ($model->volumeDiscounts && $model->volumeDiscounts->isNotEmpty()) {
                    $volumeDiscount = $model->volumeDiscounts
                        ->where('min_quantity', '<=', $runtimeItem->qty)
                        ->sortByDesc('min_quantity')
                        ->first();

                    if ($volumeDiscount) {
                        if (! ($runtimeItem->options['originalPrice'] ?? false)) {
                            $opts = $dbItem->options ?? [];
                            $opts['originalPrice'] = $dbItem->unit_price ?? (float) $model->price;
                            $dbItem->options = $opts;
                            $dbItem->save();
                            $cartChanged = true;
                        }

                        $base = (float) ($runtimeItem->options['originalPrice'] ?? $dbItem->unit_price ?? $model->price);
                        $price = $volumeDiscount->getPrice($base);
                    }
                }

                if ($price !== null && (float) $dbItem->unit_price !== (float) $price) {
                    $dbItem->unit_price = (float) $price;
                    $dbItem->save();
                    $cartChanged = true;
                }
            }
        }

        // Parent product (groep) stock check
        $parentItemsToCheck->unique()->each(function ($parentId) use (&$cartChanged, $cart) {
            $parentProduct = Product::find($parentId);
            if (! $parentProduct) {
                return;
            }

            $dbItems = CartItemModel::query()
                ->where('cart_id', $cart->id)
                ->whereNotNull('product_id')
                ->get();

            // runtime needed: check children belong to parent group
            $products = Product::query()->with('parent')->whereIn('id', $dbItems->pluck('product_id')->filter()->unique())->get()->keyBy('id');

            $itemsForParent = $dbItems->filter(function (CartItemModel $item) use ($products, $parentId) {
                $p = $products[$item->product_id] ?? null;

                return $p && $p->parent && $p->parent->id === $parentId;
            });

            $maxStock = $parentProduct->stock();
            $maxLimit = $parentProduct->limit_purchases_per_customer_limit;
            $currentAmount = (int) $itemsForParent->sum('quantity');

            if ($currentAmount > $maxStock || $currentAmount > $maxLimit) {
                Notification::make()
                    ->danger()
                    ->title(Translation::get('parent-product-limit-reached', 'cart', 'You cannot have more than the allowed amount of this product in your cart'))
                    ->send();

                EcommerceActionLog::createLog('remove_from_cart', $currentAmount, productId: $parentProduct->id);

                $itemsForParent->each(function (CartItemModel $item) {
                    $item->delete();
                });

                $cartChanged = true;
            }
        });

        if ($cartChanged) {
            $this->updateData();
        }

        return $cartChanged;
    }

    // -------------------------------------------------------------------------
    // Cart type
    // -------------------------------------------------------------------------

    public function setCartType(?string $cartType = null): void
    {
        if ($cartType) {
            static::$cartType = $cartType;
        } elseif (! static::$cartType) {
            static::$cartType = 'default';
        }

        // re-load cart pointer for this type
        static::$cart = null;
    }

    public function getCartType(): string
    {
        return static::$cartType ?? 'default';
    }

    public function isInitialized(): bool
    {
        return static::$initialized;
    }

    // -------------------------------------------------------------------------
    // Helpers: options hashing (merge items)
    // -------------------------------------------------------------------------

    protected function normalizeOptions(array $options): array
    {
        $options = Arr::dot($options);
        ksort($options);

        return Arr::undot($options);
    }

    protected function optionsHash(array $options): string
    {
        return hash('sha256', json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
