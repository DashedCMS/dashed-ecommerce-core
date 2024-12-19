<?php

namespace Dashed\DashedEcommerceCore\Models;

use Carbon\Carbon;
use Dashed\DashedCore\Classes\Sites;
use Gloudemans\Shoppingcart\CartItem;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Traits\HasDynamicRelation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductUpdatedEvent;

class ProductVariant extends Model
{
    use SoftDeletes;
    use HasDynamicRelation;

    protected $table = 'dashed__product_variants';

    public $translatable = [
        'name',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
        'expected_in_stock_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::created(function ($product) {
            //            ProductCreatedEvent::dispatch($product);
        });

        static::updated(function ($product) {
            //            ProductUpdatedEvent::dispatch($product);
        });

        static::saved(function ($product) {
            //            if ($product->is_bundle && $product->type == 'variable' && ! $product->parent_id) {
            //                $product->is_bundle = false;
            //                $product->save();
            //                $product->bundleProducts()->detach();
            //            }

            //            if ($product->childProducts()->count()) {
            //                $product->parent_id = null;
            //                $product->saveQuietly();
            //            }

            //            ProductSavedEvent::dispatch($product);
            //            UpdateProductInformationJob::dispatch($product);
        });

        static::deleting(function ($product) {
            //            foreach ($product->childProducts as $childProduct) {
            //                $childProduct->delete();
            //            }
            //            $product->productCategories()->detach();
            //            $product->productFilters()->detach();
            //            $product->activeProductFilters()->detach();
        });
    }

    //    public function scopeSearch($query, ?string $search = null)
    //    {
    //        $minPrice = request()->get('min-price') ? request()->get('min-price') : null;
    //        $maxPrice = request()->get('max-price') ? request()->get('max-price') : null;
    //
    //        $search = request()->get('search') ?: $search;
    //
    //        if ($minPrice) {
    //            $query->where('price', '>=', $minPrice);
    //        }
    //        if ($maxPrice) {
    //            $query->where('price', '<=', $maxPrice);
    //        }
    //
    //        $query->where(function ($query) use ($search) {
    //            $loop = 1;
    //            foreach (self::getTranslatableAttributes() as $attribute) {
    //                if (! method_exists($this, $attribute)) {
    //                    if ($loop == 1) {
    //                        $query->whereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
    //                    } else {
    //                        $query->orWhereRaw('LOWER(`' . $attribute . '`) LIKE ? ', ['%' . trim(strtolower($search)) . '%']);
    //                    }
    //                    $loop++;
    //                }
    //            }
    //            $query->orWhere('sku', 'LIKE', '%' . $search . '%')
    //                ->orWhere('ean', $search);
    //        });
    //    }

    public function scopeIsNotBundle($query)
    {
        $query->where('is_bundle', 0);
    }

    public function scopeIsBundle($query)
    {
        $query->where('is_bundle', 1);
    }

    public function scopePublicShowable($query, bool $overridePublic = false)
    {
        if (auth()->check() && auth()->user()->role == 'admin' && $overridePublic) {
            return;
        }

        //        if (auth()->guest() || (auth()->check() && auth()->user()->role !== 'admin' && $overridePublic)) {
        $query
            ->public()
            ->thisSite()
            ->where(function ($query) {
                $query->where(function ($query) {
                    $query
                        ->where('sku', '!=', null)
                        ->where('price', '!=', null);
                })
                    ->orWhere(function ($query) {
                        $query
                            ->where('type', 'variable')
                            ->where('parent_id', null);
                    });
            });

        //        if (Customsetting::get('products_hide_parents_in_overview', null, false)) {
        //            $query = $query->notParentProduct();
        //        }

        return $query;
        //        }
    }

    //    public function scopeHandOrderShowable($query)
    //    {
    //        $query
    //            ->where(function ($query) {
    //                $query->where('type', '!=', 'variable')
    //                    ->where('sku', '!=', null)
    //                    ->where('price', '!=', null);
    //            })->orWhere(function ($query) {
    //                $query->where('type', 'variable')
    //                    ->where('parent_id', '!=', null)
    //                    ->where('sku', '!=', null)
    //                    ->where('price', '!=', null);
    //            });
    //    }

    //    public function scopeAvailableForShoppingFeed($query)
    //    {
    //        $query->where('ean', '!=', null)->whereIn('type', ['simple', 'variable']);
    //    }

    public function getCurrentPriceAttribute()
    {
        return $this->getRawOriginal('current_price');
    }

    public function getDiscountPriceAttribute()
    {
        return $this->getRawOriginal('discount_price');
    }

    public function getFirstImageAttribute()
    {
        return $this->images[0] ?? '';
    }

    public function getImagesExceptFirstAttribute(): array
    {
        $images = $this->images ?: [];
        if (count($images)) {
            unset($images[0]);
        }

        return $images;
    }

    //    public function reservedStock()
    //    {
    //        return OrderProduct::where('product_id', $this->id)->whereIn('order_id', Order::whereIn('status', ['pending'])->pluck('id'))->count();
    //    }

    public function stock()
    {
        return $this->total_stock;
    }

    //    public function calculateStock()
    //    {
    //        $stock = 0;
    //
    //        if ($this->is_bundle) {
    //            $minStock = 100000;
    //            foreach ($this->bundleProducts as $bundleProduct) {
    //                if ($bundleProduct->stock() < $minStock) {
    //                    $minStock = $bundleProduct->stock();
    //                }
    //            }
    //
    //            $stock = $minStock;
    //        } elseif ($this->use_stock) {
    //            if ($this->outOfStockSellable()) {
    //                $stock = 100000;
    //            } else {
    //                $stock = $this->stock - $this->reservedStock();
    //            }
    //        } else {
    //            if ($this->stock_status == 'in_stock') {
    //                $stock = 100000;
    //            } else {
    //                $stock = 0;
    //            }
    //        }
    //
    //        $this->total_stock = $stock;
    //        $this->saveQuietly();
    //        $this->calculateInStock();
    //    }

    //    public function calculatePrices()
    //    {
    //        if ($this->is_bundle && $this->use_bundle_product_price) {
    //            $currentPrice = $this->bundleProducts()->sum('price');
    //        } elseif ($this->childProducts()->count()) {
    //            $currentPrice = $this->childProducts()->orderBy('price', 'ASC')->value('price');
    //        } else {
    //            $currentPrice = $this->price;
    //        }
    //        $this->current_price = $currentPrice;
    //
    //        if ($this->is_bundle && $this->use_bundle_product_price) {
    //            $discountPrice = $this->bundleProducts()->sum('new_price');
    //        } elseif ($this->childProducts()->count()) {
    //            $discountPrice = $this->childProducts()->orderBy('price', 'ASC')->value('new_price');
    //        } else {
    //            if ($this->new_price) {
    //                $discountPrice = $this->new_price;
    //            } else {
    //                $discountPrice = null;
    //            }
    //        }
    //
    //        $this->discount_price = $discountPrice;
    //        $this->saveQuietly();
    //    }

    //    public function hasDirectSellableStock(): bool
    //    {
    //        if ($this->is_bundle) {
    //            $allBundleProductsDirectSellable = true;
    //
    //            foreach ($this->bundleProducts as $bundleProduct) {
    //                if (! $bundleProduct->hasDirectSellableStock()) {
    //                    $allBundleProductsDirectSellable = false;
    //                }
    //            }
    //
    //            if ($allBundleProductsDirectSellable) {
    //                return true;
    //            }
    //        } elseif ($this->childProducts()->count()) {
    //            foreach ($this->childProducts as $childProduct) {
    //                if ($childProduct->hasDirectSellableStock()) {
    //                    return true;
    //                }
    //            }
    //        } else {
    //            if ($this->directSellableStock() > 0) {
    //                return true;
    //            }
    //        }
    //
    //        return false;
    //    }

    //    public function directSellableStock()
    //    {
    //        if ($this->use_stock) {
    //            return $this->stock - $this->reservedStock();
    //        } else {
    //            if ($this->stock_status == 'in_stock') {
    //                return 100000;
    //            } else {
    //                return 0;
    //            }
    //        }
    //    }

    public function inStock(): bool
    {
        return $this->in_stock;
    }

    //    public function calculateInStock(): void
    //    {
    //        $inStock = false;
    //
    //        if ($this->is_bundle) {
    //            $allBundleProductsInStock = true;
    //
    //            foreach ($this->bundleProducts as $bundleProduct) {
    //                if (! $bundleProduct->inStock()) {
    //                    $allBundleProductsInStock = false;
    //                }
    //            }
    //
    //            $inStock = $allBundleProductsInStock;
    //        } elseif ($this->childProducts()->count()) {
    //            foreach ($this->childProducts as $childProduct) {
    //                if ($childProduct->inStock()) {
    //                    $inStock = true;
    //                }
    //            }
    //        } else {
    //            if ($this->type == 'simple') {
    //                $inStock = $this->stock() > 0;
    //            } elseif ($this->type == 'variable') {
    //                if ($this->parent) {
    //                    $inStock = $this->stock() > 0;
    //                } else {
    //                    foreach ($this->childProducts() as $childProduct) {
    //                        if ($childProduct->inStock()) {
    //                            $inStock = true;
    //                        }
    //                    }
    //                }
    //            }
    //        }
    //
    //        $this->in_stock = $inStock;
    //        $this->saveQuietly();
    //    }

    //    public function calculateTotalPurchases(): void
    //    {
    //        $purchases = $this->purchases;
    //
    //        foreach ($this->childProducts as $childProduct) {
    //            $purchases = $purchases + $childProduct->purchases;
    //        }
    //
    //        $this->total_purchases = $purchases;
    //        $this->saveQuietly();
    //    }

    //    public function calculateDeliveryTime(): void
    //    {
    //        if ($this->is_bundle) {
    //            $deliveryDays = 0;
    //            $deliveryDate = null;
    //
    //            foreach ($this->bundleProducts as $bundleProduct) {
    //                if ($bundleProduct->inStock() && ! $bundleProduct->hasDirectSellableStock()) {
    //                    if ($bundleProduct->expectedDeliveryInDays() > $deliveryDays) {
    //                        $deliveryDays = $bundleProduct->expectedDeliveryInDays();
    //                    }
    //                    if ($bundleProduct->expectedInStockDate() && (! $deliveryDate || $bundleProduct->expectedInStockDate() > $deliveryDate)) {
    //                        $deliveryDate = $bundleProduct->expectedInStockDate();
    //                    }
    //                }
    //            }
    //
    //            if ($deliveryDays && $deliveryDate && $deliveryDate <= now()->addDays($deliveryDays)) {
    //                $this->expected_delivery_in_days = $deliveryDays;
    //                $this->expected_in_stock_date = null;
    //            } elseif ($deliveryDays && $deliveryDate && $deliveryDate > now()->addDays($deliveryDays)) {
    //                $this->expected_in_stock_date = $deliveryDate;
    //                $this->expected_delivery_in_days = null;
    //            } elseif ($deliveryDays) {
    //                $this->expected_delivery_in_days = $deliveryDays;
    //                $this->expected_in_stock_date = null;
    //            } elseif ($deliveryDate) {
    //                $this->expected_in_stock_date = $deliveryDate;
    //                $this->expected_delivery_in_days = null;
    //            } else {
    //                $this->expected_in_stock_date = null;
    //                $this->expected_delivery_in_days = null;
    //            }
    //            $this->saveQuietly();
    //        }
    //    }

    //    public function outOfStockSellable(): bool
    //    {
    //        if (! $this->use_stock) {
    //            if ($this->stock_status == 'out_of_stock') {
    //                return false;
    //            }
    //        }
    //
    //        if (! $this->out_of_stock_sellable) {
    //            return false;
    //        }
    //
    //        if ((Customsetting::get('product_out_of_stock_sellable_date_should_be_valid', Sites::getActive(), 1) && ! $this->expectedInStockDateValid()) && ! $this->expectedDeliveryInDays()) {
    //            return false;
    //        }
    //
    //        return true;
    //    }

    //    public function isPreorderable()
    //    {
    //        return $this->inStock() && ! $this->hasDirectSellableStock() && $this->use_stock;
    //    }

    //    public function expectedInStockDate()
    //    {
    //        return $this->expected_in_stock_date ? $this->expected_in_stock_date->format('d-m-Y') : null;
    //    }

    //    public function expectedInStockDateValid()
    //    {
    //        return $this->expected_in_stock_date >= now();
    //    }

    //    public function expectedInStockDateInWeeks(): float
    //    {
    //        $expectedInStockDate = self::expectedInStockDate();
    //        if (! $expectedInStockDate || Carbon::parse($expectedInStockDate) < now()) {
    //            return 0;
    //        }
    //
    //        $diffInWeeks = floor(now()->subDay()->diffInWeeks(Carbon::parse($expectedInStockDate)));
    //        if ($diffInWeeks < 0) {
    //            $diffInWeeks = 0;
    //        }
    //
    //        return $diffInWeeks;
    //    }

    //    public function expectedDeliveryInDays(): int
    //    {
    //        $expectedDeliveryInDays = $this->expected_delivery_in_days ?: 0;
    //
    //        return $expectedDeliveryInDays;
    //    }

    //    public function purchasable()
    //    {
    //        if ($this->inStock() || $this->outOfStockSellable()) {
    //            return true;
    //        }
    //
    //        return false;
    //    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function shippingClasses()
    {
        return $this->belongsToMany(ShippingClass::class, 'dashed__product_shipping_class');
    }

    //    public function productFilters()
    //    {
    //        return $this->belongsToMany(ProductFilter::class, 'dashed__product_filter')
    //            ->orderBy('created_at')
    //            ->withPivot(['product_filter_option_id']);
    //    }

    //    public function enabledProductFilterOptions()
    //    {
    //        return $this->belongsToMany(ProductFilter::class, 'dashed__product_enabled_filter_options')
    //            ->withPivot(['product_filter_option_id']);
    //    }

    //    public function activeProductFilters()
    //    {
    //        return $this->belongsToMany(ProductFilter::class, 'dashed__active_product_filter')
    //            ->orderBy('order')
    //            ->withPivot(['use_for_variations']);
    //    }

    //    public function activeProductFiltersForVariations()
    //    {
    //        return $this->belongsToMany(ProductFilter::class, 'dashed__active_product_filter')
    //            ->orderBy('created_at')
    //            ->wherePivot('use_for_variations', 1)
    //            ->withPivot(['use_for_variations']);
    //    }

    //    public function bundleProducts(): BelongsToMany
    //    {
    //        return $this->belongsToMany(Product::class, 'dashed__product_bundle_products', 'product_id', 'bundle_product_id');
    //    }

    //    public static function getShoppingCartItemPrice(CartItem $cartItem, string|DiscountCode|null $discountCode = null)
    //    {
    //        if ($discountCode && is_string($discountCode)) {
    //            $discountCode = null;
    //        }
    //
    //        $quantity = $cartItem->qty;
    //        $options = $cartItem->options['options'];
    //
    //        $price = 0;
    //
    //        $price += ($cartItem->model ? $cartItem->model->currentPrice : $cartItem->options['singlePrice']) * $quantity;
    //
    //        foreach ($options as $productExtraOptionId => $productExtraOption) {
    //            if (! str($productExtraOptionId)->contains('product-extra-')) {
    //                $thisProductExtraOption = ProductExtraOption::find($productExtraOptionId);
    //                if ($thisProductExtraOption) {
    //                    if ($thisProductExtraOption->calculate_only_1_quantity) {
    //                        $price += $thisProductExtraOption->price;
    //                    } else {
    //                        $price += ($thisProductExtraOption->price * $quantity);
    //                    }
    //                }
    //            }
    //        }
    //
    //        if ($discountCode && $discountCode->type == 'percentage') {
    //            $discountValidForProduct = false;
    //
    //            if ($discountCode->valid_for == 'categories') {
    //                if ($cartItem->model && $discountCode->productCategories()->whereIn('product_category_id', $cartItem->model->productCategories()->pluck('product_category_id'))->exists()) {
    //                    $discountValidForProduct = true;
    //                }
    //            } elseif ($discountCode->valid_for == 'products') {
    //                if ($cartItem->model && $discountCode->products()->where('product_id', $cartItem->model->id)->exists()) {
    //                    $discountValidForProduct = true;
    //                }
    //            } else {
    //                $discountValidForProduct = true;
    //            }
    //
    //            if ($discountValidForProduct) {
    //                $price = round(($price / $quantity / 100) * (100 - $discountCode->discount_percentage), 2) * $quantity;
    //            }
    //
    //            if ($price < 0) {
    //                $price = 0.01;
    //            }
    //        }
    //
    //        return $price;
    //    }

    public function productExtras(): HasMany
    {
        return $this->hasMany(ProductExtra::class)
            ->with(['productExtraOptions']);
    }

    public function globalProductExtras(): BelongsToMany
    {
        return $this->belongsToMany(ProductExtra::class, 'dashed__product_extra_product_variant', 'product_variant_id', 'product_extra_id')
            ->where('global', 1)
            ->with(['productExtraOptions']);
    }
}
