<?php

namespace Dashed\DashedEcommerceCore\Models;

use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
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

    /**
     * Of de klant een track & trace-mail krijgt bij het aanmaken van deze
     * regel. Geen kolom: dit hoort bij de aanroep, niet bij de zending.
     *
     * Blijft dit null, dan beslist de instelling per taal. Een aanroeper die
     * de klant bewust wel of niet wil mailen (de knop "Voeg track and trace
     * toe" in het CMS en dezelfde actie in de app) zet 'm expliciet.
     */
    public ?bool $mailCustomer = null;

    public static function boot()
    {
        parent::boot();

        static::created(function ($trackAndTrace) {
            // Elke nieuwe track & trace-regel hoort in het orderlogboek, ook
            // (juist) de automatisch aangemaakte vanuit een labelkoppeling.
            $orderLog = new OrderLog();
            $orderLog->order_id = $trackAndTrace->order->id;
            $orderLog->user_id = auth()->user()->id ?? null;
            $orderLog->tag = 'order.track-and-trace.created';
            $orderLog->note = trim(($trackAndTrace->delivery_company ?: $trackAndTrace->supplier) . ': ' . $trackAndTrace->code);
            $orderLog->is_system = auth()->guest();
            $orderLog->save();

            if ($trackAndTrace->order->email && $trackAndTrace->shouldMailCustomer()) {
                try {
                    Mail::to($trackAndTrace->order->email)->send(new TrackandTraceMail($trackAndTrace));

                    OrderLog::createLog(orderId: $trackAndTrace->order->id, tag: 'order.t&t.send', isSystem: auth()->guest());
                } catch (\Exception $e) {
                    $orderLog = new OrderLog();
                    $orderLog->order_id = $trackAndTrace->order->id;
                    $orderLog->user_id = auth()->check() ? auth()->user()->id : null;
                    $orderLog->tag = 'order.t&t.not-send';
                    $orderLog->is_system = auth()->guest();
                    $orderLog->save();
                }
            }
        });
    }

    /**
     * De instelling wordt tegen de taal van de bestelling gelezen en niet
     * tegen de actieve taal: het label wordt vaak door een beheerder of door
     * een wachtrij aangemaakt, en dan zegt de actieve taal niets over de
     * klant die de mail zou krijgen.
     */
    public function shouldMailCustomer(): bool
    {
        if (! is_null($this->mailCustomer)) {
            return $this->mailCustomer;
        }

        return (bool) Customsetting::get('track_and_trace_mail_enabled', null, '1', $this->order?->locale);
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
