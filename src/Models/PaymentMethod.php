<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Qubiqx\QcommerceCore\Classes\Sites;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Translatable\HasTranslations;

class PaymentMethod extends Model
{
    use HasTranslations;
    use LogsActivity;
    use SoftDeletes;

    protected static $logFillable = true;

    protected $fillable = [
        'site_id',
        'name',
        'additional_info',
        'payment_instructions',
        'extra_costs',
        'available_from_amount',
        'deposit_calculation',
    ];

    public $translatable = [
        'name',
        'additional_info',
        'payment_instructions',
    ];

    protected $table = 'qcommerce__payment_methods';

    public function site()
    {
        foreach (Sites::getSites() as $site) {
            if ($site['id'] == $this->site_id) {
                return $site;
            }
        }
    }
}
