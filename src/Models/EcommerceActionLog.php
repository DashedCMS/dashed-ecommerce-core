<?php

namespace Dashed\DashedEcommerceCore\Models;

use App\Models\User;
use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductInformationJob;
use Dashed\DashedEcommerceCore\Jobs\UpdateProductPricesJob;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EcommerceActionLog extends Model
{
    protected $table = 'dashed__ecommerce_action_logs';

    public static function boot()
    {
        parent::boot();

        static::saved(function ($log) {
            if ($log->product) {
                $log->updateProduct();
            }
            if ($log->productGroup) {
                $log->updateProductGroup();
            }
        });

        static::deleted(function ($log) {
            if ($log->product) {
                $log->updateProduct();
            }
            if ($log->productGroup) {
                $log->updateProductGroup();
            }
        });
    }

    public function updateProduct()
    {
        $this->product->add_to_cart_count = $this->product->ecommerceActionLogs()
            ->where('action_type', 'add_to_cart')
            ->sum('quantity');
        $this->product->remove_from_cart_count = $this->product->ecommerceActionLogs()
            ->where('action_type', 'remove_from_cart')
            ->sum('quantity');
        $this->product->save();
    }

    public function updateProductGroup()
    {
        $this->productGroup->add_to_cart_count = $this->productGroup->products()->sum('add_to_cart_count');
        $this->productGroup->remove_from_cart_count = $this->productGroup->products()->sum('remove_from_cart_count');
        $this->productGroup->save();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function createLog(string $actionType, int $quantity = 1, ?int $productGroupId = null, ?int $productId = null, ?int $orderId = null): EcommerceActionLog
    {
        $actionLog = new self();
        $actionLog->action_type = $actionType;
        $actionLog->quantity = $quantity;
        $actionLog->product_group_id = $productGroupId;
        $actionLog->product_id = $productId;
        $actionLog->order_id = $orderId;
        $actionLog->user_id = auth()->id();
        $actionLog->ip = request()->ip();
        $actionLog->save();

        return $actionLog;
    }
}
