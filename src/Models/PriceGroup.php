<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceGroup extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__price_groups';

    protected $fillable = [
        'name',
        'show_prices_ex_vat',
        'order',
    ];

    protected $casts = [
        'show_prices_ex_vat' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'price_group_id');
    }

    public function syncUsers(array $userIds): void
    {
        User::whereIn('id', $userIds)
            ->update(['price_group_id' => $this->id]);

        User::where('price_group_id', $this->id)
            ->whereNotIn('id', $userIds)
            ->update(['price_group_id' => null]);
    }
}
