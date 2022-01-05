<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceEcommerceCore\Classes\ShoppingCart;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class ShippingMethod extends Model
{
    use HasTranslations;
    use LogsActivity;

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
    ];

    public $translatable = [
        'name',
    ];

    protected $casts = [
      'variables' => 'array',
    ];

    protected $table = 'qcommerce__shipping_methods';

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
        return $this->belongsTo(ShippingZone::class);
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

            foreach ($this->variables as $variable) {
                while ($cartItemsCount >= $variable['amount_of_items']) {
                    $cartItemsCount -= $variable['amount_of_items'];
                    $shippingCosts += $variable['costs'];
                }
            }

            $variableStaticCosts = $this->variable_static_costs;
            $variableStaticCosts = str_replace('{SHIPPING_COSTS}', $shippingCosts, $variableStaticCosts);

            $shippingCosts += eval('return ' . $variableStaticCosts . ';');

            return $shippingCosts;
        } else {
            return $this->costs;
        }
    }
}
