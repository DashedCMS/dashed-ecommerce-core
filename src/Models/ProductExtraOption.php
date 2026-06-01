<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductExtraOption extends Model
{
    use HasTranslations;
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'product_extra_id',
        'value',
        'price',
        'calculate_only_1_quantity',
    ];

    public $translatable = [
        'value',
    ];

    protected $table = 'dashed__product_extra_options';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function productExtra()
    {
        return $this->belongsto(ProductExtra::class);
    }

    public function priceForUser(?User $user = null): float
    {
        if (! $user && auth()->check()) {
            $user = auth()->user();
        }

        $base = (float) ($this->price ?? 0);

        if ($user) {
            $userRow = DB::table('dashed__product_extra_option_user')
                ->where('user_id', $user->id)
                ->where('product_extra_option_id', $this->id)
                ->first();

            $resolved = $this->resolveExtraRow($userRow, $base);
            if ($resolved !== null) {
                return $resolved;
            }

            if ($user->price_group_id) {
                $groupRow = DB::table('dashed__product_extra_option_price_group')
                    ->where('price_group_id', $user->price_group_id)
                    ->where('product_extra_option_id', $this->id)
                    ->first();

                $resolved = $this->resolveExtraRow($groupRow, $base);
                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return $base;
    }

    protected function resolveExtraRow($row, float $base): ?float
    {
        if (! $row) {
            return null;
        }

        if ($row->price !== null) {
            return (float) $row->price;
        }

        if ($row->discount_percentage !== null) {
            return max(0, $base - ($base * ((float) $row->discount_percentage / 100)));
        }

        return null;
    }
}
