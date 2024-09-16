<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderLog extends Model
{
    use SoftDeletes;
    use LogsActivity;

    protected static $logFillable = true;

    protected $table = 'dashed__order_logs';

    protected $fillable = [
        'order_id',
        'user_id',
        'tag',
        'public_for_customer',
        'note',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($orderLog) {
            $orderLog->is_system = str($orderLog->note)->contains('system');
            $orderLog->url = url()->current();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function tag()
    {
        if ($this->tag == 'order.created') {
            $string = 'heeft een bestelling geplaatst, wachten op betaling.';
        } elseif ($this->tag == 'order.created.by.admin') {
            $string = 'heeft een bestelling geplaatst via een administrator, wachten op betaling.';
        } elseif ($this->tag == 'order.created.by.channable') {
            $string = 'heeft een bestelling geplaatst via channable.';
        } elseif ($this->tag == 'order.partially_paid') {
            $string = 'heeft de bestelling gedeeltelijk betaald.';
        } elseif ($this->tag == 'order.paid') {
            $string = 'heeft de bestelling betaald.';
        } elseif ($this->tag == 'order.paid.invoice.mail.send') {
            $string = 'heeft de bestellings mail laten versturen.';
        } elseif ($this->tag == 'order.paid.invoice.mail.send.failed') {
            $string = 'heeft de bestellings mail NIET laten versturen.';
        } elseif ($this->tag == 'order.system.paid.invoice.mail.send') {
            $string = 'heeft de bestellings mail laten versturen.';
        } elseif ($this->tag == 'order.waiting_for_confirmation') {
            $string = 'heeft gekozen voor een betalingsmethode met handmatige verificatie, wachten op betaling.';
        } elseif ($this->tag == 'order.cancelled') {
            $string = 'heeft de bestelling geannuleerd.';
        } elseif ($this->tag == 'order.cancelled.mail.send') {
            $string = 'heeft de annulerings mail verstuurd.';
        } elseif ($this->tag == 'order.cancelled.mail.send.failed') {
            $string = 'heeft de annulerings mail niet verstuurd vanwege een fout.';
        } elseif ($this->tag == 'order.system.cancelled') {
            $string = 'heeft de bestelling automatisch geannuleerd.';
        } elseif ($this->tag == 'order.note.created') {
            $string = 'heeft een notitie aangemaakt.';
        } elseif ($this->tag == 'system.note.created') {
            $string = 'heeft een notitie aangemaakt.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-handled') {
            $string = 'heeft de bestelling als afgehandeld gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-unhandled') {
            $string = 'heeft de bestelling als niet afgehandeld gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-packed') {
            $string = 'heeft de bestelling als ingepakt gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-shipped') {
            $string = 'heeft de bestelling als verzonden gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-in_treatment') {
            $string = 'heeft de bestelling als in behandeling gemarkeerd.';
        } elseif ($this->tag == 'order.marked-as-paid') {
            $string = 'heeft de bestelling als betaald gemarkeerd.';
        } elseif ($this->tag == 'order.pushed-to-keendelivery') {
            $string = 'heeft de bestelling doorgezet naar KeenDelivery.';
        } elseif ($this->tag == 'order.pushed-to-efulfillmentshop') {
            $string = 'heeft de bestelling doorgezet naar Efulfillmentshop.';
        } elseif ($this->tag == 'order.pushed-to-montaportal') {
            $string = 'heeft de bestelling doorgezet naar Montaportal.';
        } elseif ($this->tag == 'order.t&t.send') {
            $string = 'heeft de T&T codes verstuurd naar de klant.';
        } elseif ($this->tag == 'order.t&t.not-send') {
            $string = 'heeft de T&T codes NIET verstuurd naar de klant.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-unhandled.mail.send') {
            $string = 'heeft de fulfillment status update voor Niet afgehandeld laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-unhandled.mail.not-send') {
            $string = 'heeft de fulfillment status update voor Niet afgehandeld niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-handled.mail.send') {
            $string = 'heeft de fulfillment status update voor Afgehandeld laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-handled.mail.not-send') {
            $string = 'heeft de fulfillment status update voor Afgehandeld niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-packed.mail.send') {
            $string = 'heeft de fulfillment status update voor Ingepakt laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-packed.mail.not-send') {
            $string = 'heeft de fulfillment status update voor Ingepakt niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-in_treatment.mail.send') {
            $string = 'heeft de fulfillment status update voor In behandeling laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-in_treatment.mail.not-send') {
            $string = 'heeft de fulfillment status update voor In behandeling niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-shipped.mail.send') {
            $string = 'heeft de fulfillment status update voor Verzonden laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-shipped.mail.not-send') {
            $string = 'heeft de fulfillment status update voor Verzonden niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-shipped.mail.not-send') {
            $string = 'heeft de fulfillment status update voor Verzonden niet laten versturen.';
        } elseif ($this->tag == 'order.marked_as_paid_event.dispatched') {
            $string = 'heeft een signaal afgegeven dat de order is betaald.';
        } elseif ($this->message) {
            return $this->message;
        } else {
            return 'ERROR tag niet gevonden: ' . $this->tag;
        }

        return ($this->user ? $this->user->name : ($this->is_system ? 'Systeem' : $this->order->name)) . ' ' . $string;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

    public static function createLog(int $orderId, string $tag = 'system.note.created', ?string $note = null, $images = null, $publicForCustomer = false)
    {
        $orderLog = new OrderLog();
        $orderLog->order_id = $orderId;
        $orderLog->user_id = str($tag)->contains('system') ? null : (auth()->user()->id ?? null);
        $orderLog->tag = $tag;
        $orderLog->note = $note;
        $orderLog->images = $images;
        $orderLog->public_for_customer = $publicForCustomer;
        $orderLog->save();
    }
}
