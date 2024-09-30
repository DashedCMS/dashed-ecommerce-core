<?php

namespace Dashed\DashedEcommerceCore\Models;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DiscountCode extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__discount_codes';

    protected $fillable = [
        'site_ids',
        'name',
        'code',
        'limit_use_per_customer',
        'use_stock',
        'stock',
        'stock_used',
        'minimal_requirements',
        'minimum_amount',
        'minimum_products_count',
        'type',
        'discount_percentage',
        'discount_amount',
        'valid_for',
        'valid_for_customers',
        'valid_customers',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'site_ids' => 'array',
        'valid_customers' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($discountCode) {
            $code = '';
            foreach (str_split($discountCode->code) as $codeCharacter) {
                if ($codeCharacter == '*') {
                    $codeCharacter = strtoupper(Str::random(1));
                }
                $code .= $codeCharacter;
            }
            $discountCode->code = $code;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function scopeSearch($query)
    {
        if (request()->get('search')) {
            $search = strtolower(request()->get('search'));
            $query->where('site_ids', 'LIKE', "%$search%")
                ->orWhere('name', 'LIKE', "%$search%")
                ->orWhere('code', 'LIKE', "%$search%")
                ->orWhere('type', 'LIKE', "%$search%")
                ->orWhere('start_date', 'LIKE', "%$search%")
                ->orWhere('end_date', 'LIKE', "%$search%");
        }
    }

    public function scopeThisSite($query)
    {
        $query->whereJsonContains('site_ids', Sites::getActive());
    }

    public function scopeUsable($query)
    {
        $query->thisSite()
            ->where(function ($query) {
                $query->where('use_stock', 0)
                    ->orWhere('use_stock', 1)
                    ->where('stock', '>', 0);
            })->where(function ($query) {
                $query->where('start_date', null)
                    ->orWhere('start_date', '<=', now()->format('Y-m-d H:i:s'));
            })->where(function ($query) {
                $query->where('end_date', null)
                    ->orWhere('end_date', '>=', now()->format('Y-m-d H:i:s'));
            });
    }

    public function getDiscountedPriceForProduct(Product $product, int $quantity = 0)
    {
        $discountedPrice = $product->currentPrice * $quantity;
        if ($this->type == 'amount') {
            return $discountedPrice;
        }
        $discountValidForProduct = false;

        if ($this->valid_for == 'categories') {
            if ($this->productCategories()->whereIn('product_category_id', $product->productCategories()->pluck('product_category_id'))->exists()) {
                $discountValidForProduct = true;
            }
        } elseif ($this->valid_for == 'products') {
            if ($this->products()->where('product_id', $product->id)->exists()) {
                $discountValidForProduct = true;
            }
        } else {
            $discountValidForProduct = true;
        }

        if ($discountValidForProduct) {
            if ($this->type == 'percentage') {
                $discountedPrice = round(($discountedPrice / $quantity / 100) * (100 - $this->discount_percentage), 2) * $quantity;
            } elseif ($this->type == 'amount') {
                $discountedPrice = $discountedPrice - $this->discount_amount;
            }
        }

        if ($discountedPrice < 0) {
            return 0.01;
        }

        return number_format($discountedPrice, 2, '.', '');
    }

    public function siteNames()
    {
        $sites = [];
        foreach (Sites::getSites() as $site) {
            if (self::where('id', $this->id)->whereJsonContains('site_ids', $site['id'])->count()) {
                $sites[$site['name']] = 'active';
            } else {
                $sites[$site['name']] = 'inactive';
            }
        }

        return $sites;
    }

    public function getStatusAttribute()
    {
        if (! $this->start_date && ! $this->end_date) {
            return 'active';
        } else {
            if ($this->start_date && $this->end_date) {
                if ($this->start_date <= Carbon::now() && $this->end_date >= Carbon::now()) {
                    return 'active';
                } else {
                    return 'inactive';
                }
            } else {
                if ($this->start_date) {
                    if ($this->start_date <= Carbon::now()) {
                        return 'active';
                    } else {
                        return 'inactive';
                    }
                } else {
                    if ($this->end_date >= Carbon::now()) {
                        return 'active';
                    } else {
                        return 'inactive';
                    }
                }
            }
        }
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dashed__discount_product');
    }

    public function productCategories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'dashed__discount_category');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stock()
    {
        if ($this->use_stock) {
            return $this->stock - $this->orders->where('status', 'pending')->count();
        } else {
            return 100000;
        }
    }

    public function isValidForCart($email = null)
    {
        $itemsInCart = ShoppingCart::cartItems();

        if ($this->use_stock && $this->stock() < 1) {
            return false;
        }

        if ($email && $this->valid_for_customers == 'specific') {
            $emailIsValid = false;
            foreach (json_decode($this->valid_customers, true) as $validEmail) {
                if ($validEmail['id'] == $email) {
                    $emailIsValid = true;
                }
            }
            if (! $emailIsValid) {
                return false;
            }
        }

        if ($email && $this->limit_use_per_customer && $this->orders()->isPaid()->where('email', $email)->count()) {
            return false;
        }

        if ($this->valid_for == 'categories') {
            $amountOfCart = 0;
            $productsInCart = 0;
            foreach ($itemsInCart as $item) {
                if ($item->model && $this->productCategories()->whereIn('product_category_id', $item->model->productCategories()->pluck('product_category_id'))->exists()) {
                    $amountOfCart = $amountOfCart + ($item->model->currentPrice * $item->qty);
                    $productsInCart = $productsInCart + $item->qty;
                }
            }

            if ($productsInCart == 0) {
                return false;
            }

            if ($this->minimal_requirements == 'products') {
                if ($this->minimum_products_count > $productsInCart) {
                    return false;
                }
            } elseif ($this->minimal_requirements == 'amount') {
                if ($this->minimum_amount > $amountOfCart) {
                    return false;
                }
            }
        } elseif ($this->valid_for == 'products') {
            $amountOfCart = 0;
            $productsInCart = 0;
            foreach ($itemsInCart as $item) {
                if ($item->model && $this->products()->where('product_id', $item->model->id)->exists()) {
                    $amountOfCart = $amountOfCart + ($item->model->currentPrice * $item->qty);
                    $productsInCart = $productsInCart + $item->qty;
                }
            }

            if ($productsInCart == 0) {
                return false;
            }

            if ($this->minimal_requirements == 'products') {
                if ($this->minimum_products_count > $productsInCart) {
                    return false;
                }
            } elseif ($this->minimal_requirements == 'amount') {
                if ($this->minimum_amount > $amountOfCart) {
                    return false;
                }
            }
        } else {
            if ($itemsInCart->count() == 0) {
                return false;
            }

            if ($this->minimal_requirements == 'products') {
                if ($this->minimum_products_count > ShoppingCart::cartItemsCount()) {
                    return false;
                }
            } elseif ($this->minimal_requirements == 'amount') {
                if ($this->minimum_amount > ShoppingCart::total(false, false)) {
                    return false;
                }
            }
        }

        return true;
    }
}
