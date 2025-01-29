<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Dashed\DashedEcommerceCore\Mail\TrackandTraceMail;

class OrderTrackAndTrace extends Model
{
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__order_track_and_traces';

    protected $casts = [
        'expected_delivery_date' => 'date',
    ];

    public static function boot()
    {
        parent::boot();

        static::created(function ($trackAndTrace) {
            if ($trackAndTrace->order->email) {
                try {
                    Mail::to($trackAndTrace->order->email)->send(new TrackandTraceMail($trackAndTrace));
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $trackAndTrace->order->id;
                    $orderLog->user_id = auth()->check() ? auth()->user()->id : null;
                    $orderLog->tag = 'order.t&t.send';
                    $orderLog->save();
                } catch (\Exception $e) {
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $trackAndTrace->order->id;
                    $orderLog->user_id = auth()->check() ? auth()->user()->id : null;
                    $orderLog->tag = 'order.t&t.not-send';
                    $orderLog->save();
                }
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
