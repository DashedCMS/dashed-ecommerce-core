<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Qubiqx\Qcommerce\Classes\Sites;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class ShippingClass extends Model
{
    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'site_id',
        'name',
        'description',
        'slug',
    ];

    public $translatable = [
        'name',
        'description',
        'slug',
    ];

    protected $table = 'qcommerce__shipping_classes';

    public function site()
    {
        foreach (Sites::getSites() as $site) {
            if ($site['id'] == $this->site_id) {
                return $site;
            }
        }
    }
}
