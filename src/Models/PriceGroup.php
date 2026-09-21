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
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $before = User::where('price_group_id', $this->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        User::whereIn('id', $userIds)
            ->update(['price_group_id' => $this->id]);

        User::where('price_group_id', $this->id)
            ->whereNotIn('id', $userIds)
            ->update(['price_group_id' => null]);

        $changed = array_values(array_unique(array_merge(
            array_diff($before, $userIds),
            array_diff($userIds, $before),
        )));

        if ($changed !== []) {
            \Dashed\DashedEcommerceCore\Events\PriceGroups\PriceGroupMembersChangedEvent::dispatch($this->id, $changed);
        }
    }
}
