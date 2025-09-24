<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class OrderLogTemplate extends Model
{
    use SoftDeletes;
    use LogsActivity;
    use HasTranslations;


    protected static $logFillable = true;

    protected $table = 'dashed__order_log_templates';

    protected $fillable = [
        'name',
        'subject',
        'body',
    ];

    protected $casts = [
        'subject' => 'array',
        'body' => 'array',
    ];

    public $translatable = [
        'subject',
        'body',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
