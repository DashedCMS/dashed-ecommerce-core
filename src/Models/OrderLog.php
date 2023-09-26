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
            return ' heeft een bestelling geplaatst, wachten op betaling.';
        } elseif ($this->tag == 'order.created.by.admin') {
            return ' heeft een bestelling geplaatst via een administrator, wachten op betaling.';
        } elseif ($this->tag == 'order.created.by.channable') {
            return ' heeft een bestelling geplaatst via channable.';
        } elseif ($this->tag == 'order.partially_paid') {
            return ' heeft de bestelling gedeeltelijk betaald.';
        } elseif ($this->tag == 'order.paid') {
            return ' heeft de bestelling betaald.';
        } elseif ($this->tag == 'order.paid.invoice.mail.send') {
            return ' heeft de bestellings mail laten versturen.';
        } elseif ($this->tag == 'order.system.paid.invoice.mail.send') {
            return 'Systeem heeft de bestellings mail laten versturen.';
        } elseif ($this->tag == 'order.waiting_for_confirmation') {
            return ' heeft gekozen voor een betalingsmethode met handmatige verificatie, wachten op betaling.';
        } elseif ($this->tag == 'order.cancelled') {
            return ' heeft de bestelling geannuleerd.';
        } elseif ($this->tag == 'order.cancelled.mail.send') {
            return ' heeft de annulerings mail verstuurd.';
        } elseif ($this->tag == 'order.cancelled.mail.send.failed') {
            return ' heeft de annulerings mail niet verstuurd vanwege een fout.';
        } elseif ($this->tag == 'order.system.cancelled') {
            return ' heeft de bestelling automatisch geannuleerd.';
        } elseif ($this->tag == 'order.note.created') {
            return ' heeft een notitie aangemaakt.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-handled') {
            return ' heeft de bestelling als afgehandeld gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-unhandled') {
            return ' heeft de bestelling als niet afgehandeld gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-packed') {
            return ' heeft de bestelling als ingepakt gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-shipped') {
            return ' heeft de bestelling als verzonden gemarkeerd.';
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-in_treatment') {
            return ' heeft de bestelling als in behandeling gemarkeerd.';
        } elseif ($this->tag == 'order.marked-as-paid') {
            return ' heeft de bestelling als betaald gemarkeerd.';
        } elseif ($this->tag == 'order.pushed-to-keendelivery') {
            return ' heeft de bestelling doorgezet naar KeenDelivery.';
        } elseif ($this->tag == 'order.pushed-to-efulfillmentshop') {
            return ' heeft de bestelling doorgezet naar Efulfillmentshop.';
        } elseif ($this->tag == 'order.pushed-to-montaportal') {
            return ' heeft de bestelling doorgezet naar Montaportal.';
        } elseif ($this->tag == 'order.t&t.send') {
            return ' heeft de T&T codes verstuurd naar de klant.';
        } elseif ($this->tag == 'order.t&t.not-send') {
            return ' heeft de T&T codes NIET verstuurd naar de klant.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-unhandled.mail.send') {
            return ' heeft de fulfillment status update voor Niet afgehandeld laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-unhandled.mail.not-send') {
            return ' heeft de fulfillment status update voor Niet afgehandeld niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-handled.mail.send') {
            return ' heeft de fulfillment status update voor Afgehandeld laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-handled.mail.not-send') {
            return ' heeft de fulfillment status update voor Afgehandeld niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-packed.mail.send') {
            return ' heeft de fulfillment status update voor Ingepakt laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-packed.mail.not-send') {
            return ' heeft de fulfillment status update voor Ingepakt niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-in_treatment.mail.send') {
            return ' heeft de fulfillment status update voor In behandeling laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-in_treatment.mail.not-send') {
            return ' heeft de fulfillment status update voor In behandeling niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-shipped.mail.send') {
            return ' heeft de fulfillment status update voor Verzonden laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-shipped.mail.not-send') {
            return ' heeft de fulfillment status update voor Verzonden niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-shipped.mail.not-send') {
            return ' heeft de fulfillment status update voor Verzonden niet laten versturen.';
        } else {
            return ' ERROR tag niet gevonden: ' . $this->tag;
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }
}
