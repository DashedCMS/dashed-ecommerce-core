<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedCore\Models\Concerns\HasSearchScope;

class ShippingMethod extends Model
{
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;
    use HasSearchScope;

    protected static $logFillable = true;

    protected $fillable = [
        'shipping_zone_id',
        'name',
        'costs',
        'sort',
        'variables',
        'variable_static_costs',
        'minimum_order_value',
        'maximum_order_value',
        'order',
        'distance_range_enabled',
        'distance_range',
    ];

    public $translatable = [
        'name',
    ];

    protected $casts = [
        'variables' => 'array',
    ];

    protected $table = 'dashed__shipping_methods';

    public static function booted()
    {
        static::creating(function ($shippingMethod) {
            $shippingMethod->order = ShippingMethod::max('order') + 1;
        });

        parent::booted();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function site()
    {
        foreach (Sites::getSites() as $site) {
            if ($site['id'] == $this->site_id) {
                return $site;
            }
        }
    }

    public function shippingZone()
    {
        return $this->belongsTo(ShippingZone::class)->withTrashed();
    }

    //    public function shippingMethodClasses()
    //    {
    //        return $this->hasMany(ShippingMethodClass::class);
    //    }

    public function disabledProducts()
    {
        return $this->belongsToMany(Product::class, 'dashed__shipping_method_disabled_products', 'shipping_method_id', 'product_id');
    }

    public function disabledProductGroups()
    {
        return $this->belongsToMany(ProductGroup::class, 'dashed__shipping_method_disabled_product_groups', 'shipping_method_id', 'product_group_id');
    }

    public function costsForCart(?int $shippingZoneId = null): ?float
    {
        cartHelper()->initialize();

        $cartItems = cartHelper()->getCartItems();
        $cartItemsCount = count($cartItems);
        $activatedShippingClassIds = [];

        if ($this->sort == 'free_delivery') {
            $shippingCosts = 0;
        } elseif ($this->sort == 'variable_amount') {
            $shippingCosts = 0;

            foreach (collect($this->variables)->sortByDesc('amount_of_items') as $variable) {
                while ($cartItemsCount >= $variable['amount_of_items']) {
                    //                    dump($cartItemsCount, $variable['amount_of_items']);
                    $cartItemsCount -= $variable['amount_of_items'];
                    $shippingCosts += $variable['costs'];
                }
            }

            $variableStaticCosts = $this->variable_static_costs;
            if ($variableStaticCosts != '{SHIPPING_COSTS}') {
                $variableStaticCosts = str_replace('{SHIPPING_COSTS}', $shippingCosts, $variableStaticCosts);

                $shippingCosts += eval('return ' . $variableStaticCosts . ';');
            }

        } else {
            $shippingCosts = $this->costs;
        }

        foreach ($cartItems as $cartItem) {
            if ($this->sort != 'take_away' && $cartItem->model && $cartItem->model->shippingClasses->count()) {
                foreach ($cartItem->model->shippingClasses as $shippingClass) {
                    if ($shippingZoneId) {
                        $shippingClassPrice = $shippingClass->price_shipping_zones[$shippingZoneId] ?? 0;
                        if ($shippingClassPrice > 0) {
                            if ($shippingClass->count_once && ! in_array($shippingClass->id, $activatedShippingClassIds)) {
                                $shippingCosts = $shippingCosts + $shippingClassPrice;
                                $activatedShippingClassIds[] = $shippingClass->id;
                            } elseif ($shippingClass->count_per_product) {
                                $shippingCosts = $shippingCosts + ($shippingClassPrice * $cartItem->qty);
                            } elseif (! $shippingClass->count_once && ! $shippingClass->count_per_product) {
                                $shippingCosts = $shippingCosts + $shippingClassPrice;
                            }
                        }
                    }
                }
            }
        }

        return $shippingCosts;
    }

    public function getActivatedShippingClasses(?int $shippingZoneId = null): ?array
    {
        cartHelper()->initialize();

        $cartItems = cartHelper()->getCartItems();
        $activatedShippingClasses = [];
        $activatedShippingClassIds = [];

        foreach ($cartItems as $cartItem) {
            if ($this->sort != 'take_away' && $cartItem->model->shippingClasses->count()) {
                foreach ($cartItem->model->shippingClasses as $shippingClass) {
                    if ($shippingZoneId) {
                        $shippingClassPrice = $shippingClass->price_shipping_zones[$shippingZoneId] ?? 0;
                        if ($shippingClassPrice > 0) {
                            if ($shippingClass->count_once && ! in_array($shippingClass->id, $activatedShippingClassIds)) {
                                $shippingCosts = $shippingCosts + $shippingClassPrice;
                                $activatedShippingClassIds[$shippingClass->id] = 1;
                                $activatedShippingClasses[] = $shippingClass;
                            } elseif ($shippingClass->count_per_product) {
                                if (! in_array($shippingClass->id, array_keys($activatedShippingClassIds))) {
                                    $activatedShippingClasses[] = $shippingClass;
                                }
                                $activatedShippingClassIds[$shippingClass->id] = ($activatedShippingClassIds[$shippingClass->id] ?? 0) + $cartItem->qty;
                            } elseif (! $shippingClass->count_once && ! $shippingClass->count_per_product) {
                                if (! in_array($shippingClass->id, array_keys($activatedShippingClassIds))) {
                                    $activatedShippingClasses[] = $shippingClass;
                                }
                                $activatedShippingClassIds[$shippingClass->id] = ($activatedShippingClassIds[$shippingClass->id] ?? 0) + 1;
                            }
                        }
                    }
                }
            }
        }

        foreach ($activatedShippingClasses as $shippingClass) {
            $shippingClass->price = ($shippingClass->price_shipping_zones[$shippingZoneId] ?? 0) * $activatedShippingClassIds[$shippingClass->id];
        }

        return $activatedShippingClasses;
    }
}
