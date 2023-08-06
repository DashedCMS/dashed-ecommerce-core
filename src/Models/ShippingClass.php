<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Classes\Sites;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class ShippingClass extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'site_id',
        'name',
        'description',
    ];

    public $translatable = [
        'name',
        'description',
    ];

    protected $table = 'dashed__shipping_classes';

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
}
