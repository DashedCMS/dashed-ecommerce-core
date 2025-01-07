<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedEcommerceCore\Mail\OrderConfirmationForFulfillerMail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;

class FulfillmentCompany extends Model
{
    //    use HasTranslations;
    use LogsActivity;

    protected static $logFillable = true;

    protected $fillable = [
        'name',
    ];

    public $translatable = [];

    protected $table = 'dashed__fulfillment_companies';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public static function booted()
    {
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'fulfillment_provider');
    }

    public function sendOrder(Order $order, array $orderProducts, bool $sendProductsToCustomer = true): void
    {
        try {
            Mail::to($this->email)->send(new OrderConfirmationForFulfillerMail($order, $orderProducts, $sendProductsToCustomer));

            foreach ($orderProducts as $orderProduct) {
                $orderProduct->send_to_fulfiller = 1;
                $orderProduct->save();
            }

            OrderLog::createLog(orderId: $order->id, note: 'Producten verstuurd naar ' . $this->name . ' om te verwerken.');
        } catch (\Exception $e) {
            OrderLog::createLog(orderId: $order->id, note: 'Producten niet verzonden naar ' . $this->name . ' om de volgende reden: ' . $e->getMessage());
        }
    }
}
