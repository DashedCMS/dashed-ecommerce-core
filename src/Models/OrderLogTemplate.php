<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

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
