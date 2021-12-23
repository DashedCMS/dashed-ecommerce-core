<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Qubiqx\QcommerceCore\Classes\Sites;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class ShippingZone extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'site_id',
        'name',
        'zones',
        'search_fields',
        'hide_vat_on_invoice',
        'disabled_payment_method_ids',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'qcommerce__shipping_zones';

    protected $casts = [
        'zones' => 'array',
        'disabled_payment_method_ids' => 'array'
    ];

    public function site()
    {
        foreach (Sites::getSites() as $site) {
            if ($site['id'] == $this->site_id) {
                return $site;
            }
        }
    }

    public function shippingMethods()
    {
        return $this->hasMany(ShippingMethod::class);
    }
}
