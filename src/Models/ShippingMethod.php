<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
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

    public function shippingMethodClasses()
    {
        return $this->hasMany(ShippingMethodClass::class);
    }

    public function getCostsForCartAttribute()
    {
        if ($this->sort == 'free_delivery') {
            return 0;
        } elseif ($this->sort == 'variable_amount') {
            $shippingCosts = 0;
            $cartItemsCount = ShoppingCart::cartItemsCount();

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


            return $shippingCosts;
        } else {
            return $this->costs;
        }
    }
}
