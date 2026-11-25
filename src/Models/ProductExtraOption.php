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
        'skip_stock',
        'extra_delivery_days',
    ];

    protected $casts = [
        'skip_stock' => 'boolean',
        'extra_delivery_days' => 'integer',
    ];

    public $translatable = [
        'value',
    ];

    protected $table = 'dashed__product_extra_options';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    /** Extra working days of lead time when this option is chosen (0 = none). */
    public function extraDeliveryDays(): int
    {
        return max(0, (int) ($this->extra_delivery_days ?? 0));
    }

    /**
     * The largest extra lead time over the given option ids (0 when none). Non-numeric ids, such as
     * 'product-extra-12' for a text field, are ignored. Deleted options still count: an order keeps its promise.
     *
     * @param  iterable<mixed>  $optionIds
     */
    public static function maxExtraDeliveryDaysFor(iterable $optionIds): int
    {
        $ids = collect($optionIds)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return max(0, (int) static::withTrashed()->whereIn('id', $ids)->max('extra_delivery_days'));
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

        // getRawOriginal zodat herhaald resolven idempotent is, ook nadat de
        // component ->price al naar de gebruikersprijs heeft gezet.
        $base = (float) ($this->getRawOriginal('price') ?? 0);

        // De prijsgroep is leidend (zie Product::priceForUser): zit de
        // gebruiker in een groep, dan geldt de groepsprijs en niet een
        // achtergebleven persoonlijke prijs.
        if ($user && $user->price_group_id) {
            $groupRow = DB::table('dashed__product_extra_option_price_group')
                ->where('price_group_id', $user->price_group_id)
                ->where('product_extra_option_id', $this->id)
                ->first();

            $resolved = $this->resolveExtraRow($groupRow, $base);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($user) {
            $userRow = DB::table('dashed__product_extra_option_user')
                ->where('user_id', $user->id)
                ->where('product_extra_option_id', $this->id)
                ->first();

            $resolved = $this->resolveExtraRow($userRow, $base);
            if ($resolved !== null) {
                return $resolved;
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
