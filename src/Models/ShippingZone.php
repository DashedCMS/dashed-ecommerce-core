<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Classes\Sites;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingZone extends Model
{
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

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

    protected $table = 'dashed__shipping_zones';

    protected $casts = [
        'zones' => 'array',
        'disabled_payment_method_ids' => 'array',
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function ($shippingZone) {
            $shippingZone->shippingMethods()->delete();
        });
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

    public function shippingMethods()
    {
        return $this->hasMany(ShippingMethod::class);
    }
}
