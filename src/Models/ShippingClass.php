<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Dashed\DashedCore\Models\Concerns\HasSearchScope;

class ShippingClass extends Model
{
    use HasTranslations;
    use LogsActivity;
    use HasSearchScope;

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
