<?php

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Dashed\DashedEcommerceCore\Mail\OrderConfirmationForFulfillerMail;

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

    public function sendOrder(Order $order, array $orderProducts, bool $sendProductsToCustomer = true, null|array|Collection $files = []): void
    {
//        try {
        Mail::to($this->email)->send(new OrderConfirmationForFulfillerMail($order, $orderProducts, $sendProductsToCustomer, $files));

        foreach ($orderProducts as $orderProduct) {
            $orderProduct->send_to_fulfiller = 1;
            $orderProduct->save();
        }

        OrderLog::createLog(orderId: $order->id, note: 'Producten verstuurd naar ' . $this->name . ' om te verwerken.');
//        } catch (\Exception $e) {
//            OrderLog::createLog(orderId: $order->id, note: 'Producten niet verzonden naar ' . $this->name . ' om de volgende reden: ' . $e->getMessage());
//        }
    }
}
