<?php

namespace Qubiqx\QcommerceEcommerceCore\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductExtra extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'product_id',
        'name',
        'type',
        'required',
    ];

    public $translatable = [
        'name',
    ];

    protected $table = 'qcommerce__product_extras';

    public function product()
    {
        return $this->belongsto(Product::class);
    }

    public function productExtraOptions()
    {
        return $this->hasMany(ProductExtraOption::class);
    }
}
