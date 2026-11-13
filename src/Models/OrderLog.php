<?php

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderLog extends Model
{
    use SoftDeletes;

    // Bewust geen LogsActivity: dit is zelf een logboek, en elke regel hier nog
    // eens in het activiteitenlogboek zetten leverde alleen een tweede kopie op.

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
            // Een expliciet gezette is_system blijft staan; de notitie-heuristiek
            // is de terugval voor oude aanroepen die de vlag niet zelf zetten.
            $orderLog->is_system = $orderLog->is_system || str($orderLog->note)->contains('system');
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
        } elseif ($this->tag == 'order.changed-fulfillment-status-to-ready_for_pickup') {
            $string = 'heeft de bestelling als klaar om op te halen gemarkeerd.';
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
        } elseif ($this->tag == 'order.email.sent') {
            $string = 'heeft een e-mail verstuurd.';
        } elseif ($this->tag == 'order.return-customer-replied') {
            $string = 'De klant heeft gereageerd op het retourverzoek.';
        } elseif ($this->tag == 'order.t&t.send') {
            $string = 'heeft de T&T codes verstuurd naar de klant.';
        } elseif ($this->tag == 'order.t&t.not-send') {
            $string = 'heeft de T&T codes NIET verstuurd naar de klant.';
        } elseif ($this->tag == 'order.track-and-trace.created') {
            $string = 'heeft een Track en Trace codes aangemaakt.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-unhandled.mail.send') {
            $string = 'heeft de fulfillment status update voor Niet afgehandeld laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-unhandled.mail.not-send') {
            $string = 'heeft de fulfillment status update voor Niet afgehandeld niet laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-ready_for_pickup.mail.send') {
            $string = 'heeft de fulfillment status update voor Klaar om op te halen laten versturen.';
        } elseif ($this->tag == 'order.fulfillment-status-update-to-ready_for_pickup.mail.not-send') {
            $string = 'heeft de fulfillment status update voor Klaar om op te halen niet laten versturen.';
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
        } elseif ($this->tag == 'order.system.cancelled.mail.send') {
            $string = 'heeft de annulerings mail automatisch verstuurd.';
        } elseif ($this->tag == 'order.system.cancelled.mail.send.failed') {
            $string = 'heeft de annulerings mail automatisch niet kunnen versturen vanwege een fout.';
        } elseif ($this->tag == 'abandoned-cart.converted') {
            $string = 'heeft de bestelling alsnog afgerond na een verlaten-winkelmand-flow.';
        } elseif ($this->tag == 'order.return-requested') {
            $string = 'Retour aangevraagd';
        } elseif ($this->tag == 'order.return-approved') {
            $string = 'Retour goedgekeurd';
        } elseif ($this->tag == 'order.return-rejected') {
            $string = 'Retour afgekeurd';
        } elseif ($this->tag == 'order.return-handled') {
            $string = 'Retour afgehandeld';
        } elseif ($this->tag == 'order.return-label-failed') {
            $string = 'Retourlabel mislukt';
        } elseif ($this->tag == 'order.return-registered-by-admin') {
            $string = 'heeft een retour aangemeld.';
        } elseif ($this->tag == 'order.return-processed') {
            $string = 'heeft de retour verwerkt.' . ($this->note ? ' ' . $this->note : '');
        } elseif ($this->tag == 'order.return-closed') {
            $string = 'heeft de retour gesloten zonder creditering.';
        } elseif ($this->tag == 'order.return-handled-by-cancellation') {
            $string = 'heeft de bestelling geannuleerd; de open retour is daarmee verwerkt.' . ($this->note ? ' ' . $this->note : '');
        } elseif ($this->tag == 'order.giftcard.voided-used') {
            $string = 'heeft een gecrediteerde cadeaubon laten vervallen die al gebruikt was.' . ($this->note ? ' ' . $this->note : '');
        } elseif ($this->tag == 'order.giftcard.refunded') {
            $string = 'heeft het cadeaubonbedrag teruggezet op de cadeaubon.' . ($this->note ? ' ' . $this->note : '');
        } elseif ($this->tag == 'order.return-refunded') {
            $string = 'heeft de terugbetaling van de retour geregistreerd.' . ($this->note ? ' ' . $this->note : '');
        } elseif ($this->tag == 'order.return-mail-skipped-bol') {
            $string = 'heeft de klantmail overgeslagen: Bol-bestelling.';
        } elseif ($this->tag == 'order.return-processed.mail.failed') {
            $string = 'kon de mail "retour verwerkt" niet versturen.';
        } elseif ($this->tag == 'order.return-refunded.mail.failed') {
            $string = 'kon de mail "terugbetaald" niet versturen.';
        } elseif ($this->tag == 'order.return.full') {
            $string = 'heeft alle producten als geretourneerd geregistreerd.';
        } elseif ($this->tag == 'order.return.partial') {
            $string = 'heeft een gedeeltelijke retour geregistreerd.';
        } elseif ($this->tag == 'order.return.refund-requested') {
            $string = 'heeft een terugbetaling aangevraagd, handmatig te verwerken.';
        } elseif ($this->tag == 'order.refund.registered') {
            $string = 'heeft een terugstorting geregistreerd.' . ($this->note ? ' ' . $this->note : '');
        } elseif (str_starts_with((string) $this->tag, 'order.changed-retour-status-to-')) {
            $status = substr((string) $this->tag, strlen('order.changed-retour-status-to-'));
            $string = 'heeft de retourstatus gewijzigd naar ' . (\Dashed\DashedEcommerceCore\Classes\Orders::getReturnStatusses()[$status] ?? $status) . '.';
        } elseif ($this->tag == 'order.labelstatus.synced') {
            $string = 'heeft de labelstatussen van de zendingen bijgewerkt.';
        } elseif ($this->tag == 'order.trackandtrace.updated') {
            $string = 'heeft een track & trace-statusupdate van de vervoerder verwerkt.';
        } elseif ($this->tag == 'order.packed') {
            $string = 'heeft de bestelling ingepakt.';
        } elseif ($this->tag == 'order.unpacked') {
            $string = 'heeft het inpakken van de bestelling ongedaan gemaakt.';
        } elseif ($this->tag == 'order.on-account.placed') {
            $string = 'heeft de bestelling op rekening geplaatst.';
        } elseif ($this->tag == 'order.on-account.override') {
            $string = 'heeft de kredietlimietcontrole op rekening overschreven.' . ($this->note ? ' ' . $this->note : '');
        } elseif ($this->tag == 'order.on-account.credit-settled') {
            $string = 'heeft de bestelling op rekening verrekend via een creditorder.' . ($this->note ? ' Bedrag: ' . $this->note : '');
        } elseif ($this->tag == 'order.payment-reminder.sent') {
            $string = 'De automatische betaalherinnering is verstuurd.' . ($this->note ? ' Stap: ' . $this->note : '');
        } elseif ($this->tag == 'order.payment-reminder.manual') {
            $string = 'heeft handmatig een betaalherinnering verstuurd.' . ($this->note ? ' Stap: ' . $this->note : '');
        } elseif ($this->tag == 'order.payment-reminders.paused') {
            $string = 'heeft de betaalherinneringen gepauzeerd.';
        } elseif ($this->tag == 'order.payment-reminders.resumed') {
            $string = 'heeft de betaalherinneringen hervat.';
        } elseif ($this->tag == 'order.label.created') {
            $string = 'heeft een verzendlabel aangemaakt.';
        } elseif ($this->tag == 'order.label.downloaded') {
            $string = 'heeft het verzendlabel gedownload.';
        } elseif ($this->tag == 'order.label.deleted') {
            $string = 'heeft een verzendlabel verwijderd.';
        } elseif ($this->tag == 'order.label.requeued') {
            $string = 'heeft het verzendlabel opnieuw klaargezet.';
        } elseif ($this->tag == 'order.return-label.created') {
            $string = 'heeft een retourlabel aangemaakt.';
        } elseif ($this->tag == 'order.print.queued') {
            $string = 'heeft ' . $this->printDocument() . ' naar de printer gestuurd.';
        } elseif ($this->tag == 'order.print.done') {
            $printer = $this->noteValue('Printer');

            return ucfirst($this->printDocument()) . ' is geprint' . ($printer ? ' door ' . $printer : '') . '.';
        } elseif ($this->tag == 'order.print.failed') {
            $printer = $this->noteValue('Printer');

            return 'Printen van ' . $this->printDocument() . ($printer ? ' op ' . $printer : '') . ' is mislukt.';
        } elseif ($this->tag == 'order.print.retried') {
            $string = 'heeft een printopdracht opnieuw in de wachtrij gezet.';
        } elseif ($this->tag == 'order.print.cancelled') {
            $string = 'heeft een printopdracht geannuleerd.';
        } elseif ($this->tag == 'order.details.changed') {
            $string = 'heeft de gegevens gewijzigd.';
        } elseif ($this->tag == 'order.manual-payment.registered') {
            $string = 'heeft een betaling geregistreerd.';
        } elseif ($this->tag == 'order.invoice.regenerated') {
            $string = 'heeft de factuur opnieuw gemaakt.';
        } elseif ($this->tag == 'order.priority.changed') {
            $string = str((string) $this->note)->lower()->contains('uit')
                ? 'heeft de prioriteit uitgezet.'
                : 'heeft de prioriteit aangezet.';
        } elseif ($this->tag == 'order.sent-to-fulfillment') {
            $string = 'heeft de bestelling naar de fulfillment-partijen gestuurd.';
        } elseif ($this->note) {
            // Onbekende tag met een notitie: de notitie is de tekst, met de
            // actor ervoor zoals bij de bekende tags.
            return $this->actorName() . ' ' . $this->note;
        } else {
            return 'ERROR tag niet gevonden: ' . $this->tag;
        }

        return $this->actorName() . ' ' . $string;
    }

    /** Wie de regel heeft gemaakt: de gebruiker, anders "Systeem" of de klant. */
    public function actorName(): string
    {
        if ($this->user) {
            return (string) $this->user->name;
        }

        if ($this->is_system) {
            return 'Systeem';
        }

        return (string) ($this->order->name ?? 'Systeem');
    }

    /**
     * Waarde van een regel "Sleutel: waarde" in de notitie (printregels
     * bewaren document, printer en kanaal zo, één per regel).
     */
    public function noteValue(string $key): ?string
    {
        foreach (preg_split('/\R/', (string) $this->note) ?: [] as $line) {
            if (str_starts_with($line, $key . ': ')) {
                $value = trim(substr($line, strlen($key) + 2));

                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /** Het geprinte document met lidwoord, bv. "de pakbon". */
    protected function printDocument(): string
    {
        $document = $this->noteValue('Document');

        if (! $document) {
            return 'een document';
        }

        $document = mb_strtolower($document);

        return (str_contains($document, 'label') ? 'het ' : 'de ') . $document;
    }

    /**
     * Notitie voor een printregel: document, printer, kanaal en eventueel de
     * foutmelding, elk op een eigen regel.
     */
    public static function printNote(string $document, ?string $printer = null, ?string $channel = null, ?string $error = null): string
    {
        $lines = ['Document: ' . $document];
        if ($printer) {
            $lines[] = 'Printer: ' . $printer;
        }
        if ($channel) {
            $lines[] = 'Kanaal: ' . $channel;
        }
        if ($error) {
            $lines[] = 'Fout: ' . str($error)->limit(500)->toString();
        }

        return implode("\n", $lines);
    }

    /** Logt de huidige prioriteitsstand van de order (na het opslaan). */
    public static function logPriorityChange(Order $order): void
    {
        self::createLog(
            orderId: $order->id,
            tag: 'order.priority.changed',
            note: $order->is_priority ? 'Prioriteit aan' : 'Prioriteit uit',
        );
    }

    /** Leesbare namen van de velden die order.details.changed bijhoudt. */
    public const DETAIL_FIELDS = [
        'first_name' => 'Voornaam',
        'last_name' => 'Achternaam',
        'email' => 'E-mail',
        'phone_number' => 'Telefoonnummer',
        'company_name' => 'Bedrijfsnaam',
        'btw_id' => 'Btw-nummer',
        'street' => 'Straat',
        'house_nr' => 'Huisnummer',
        'zip_code' => 'Postcode',
        'city' => 'Plaats',
        'country' => 'Land',
        'invoice_street' => 'Factuur straat',
        'invoice_house_nr' => 'Factuur huisnummer',
        'invoice_zip_code' => 'Factuur postcode',
        'invoice_city' => 'Factuur plaats',
        'invoice_country' => 'Factuur land',
        'note' => 'Notitie',
    ];

    /**
     * Logt welke klant-, adres-, factuur- en notitievelden zijn gewijzigd
     * ("Straat: A -> B, ..."). $original is de stand van vóór het opslaan
     * (bv. $order->getOriginal() of getAttributes() vooraf). Geen regel als er
     * niets is gewijzigd.
     */
    public static function logDetailChanges(Order $order, array $original): bool
    {
        // Ruwe waarden aan beide kanten vergelijken, zodat casts (datums)
        // geen schijnwijziging geven.
        $current = $order->getAttributes();
        $changes = [];
        foreach (self::DETAIL_FIELDS as $field => $label) {
            if (! array_key_exists($field, $original) && ! array_key_exists($field, $current)) {
                continue;
            }

            $old = self::detailValue($original[$field] ?? null);
            $new = self::detailValue($current[$field] ?? null);

            if ($old === $new) {
                continue;
            }

            $changes[] = $label . ': ' . ($old === '' ? '(leeg)' : $old) . ' -> ' . ($new === '' ? '(leeg)' : $new);
        }

        if (! $changes) {
            return false;
        }

        self::createLog(orderId: $order->id, tag: 'order.details.changed', note: implode(', ', $changes));

        return true;
    }

    private static function detailValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d-m-Y');
        }

        if (is_array($value)) {
            return json_encode($value) ?: '';
        }

        return trim((string) $value);
    }

    /**
     * Id van de ingelogde gebruiker, alleen als dat een Dashed-gebruiker is.
     * De printer-API authenticeert als een Printer-model: dat id is geen user_id.
     */
    public static function currentUserId(): ?int
    {
        $user = auth()->user();

        return $user instanceof User ? (int) $user->getKey() : null;
    }

    public static function createLog(int $orderId, string $tag = 'system.note.created', ?string $note = null, ?array $images = null, bool $publicForCustomer = false, bool $isDebugLog = false, ?bool $isSystem = null, ?int $userId = null): void
    {
        if ($isDebugLog && ! config('dashed-ecommerce-core.debug_logs_enabled', false)) {
            return;
        }

        $orderLog = new OrderLog();
        $orderLog->order_id = $orderId;
        // Een expliciete systeemregel draagt geen gebruiker, ook als er
        // toevallig iemand is ingelogd.
        $orderLog->user_id = $userId ?? (($isSystem === true || str($tag)->contains('system')) ? null : self::currentUserId());
        $orderLog->tag = $tag;
        $orderLog->note = $note;
        $orderLog->images = $images;
        $orderLog->public_for_customer = $publicForCustomer;
        $orderLog->is_debug_log = $isDebugLog;
        if (! is_null($isSystem)) {
            $orderLog->is_system = $isSystem;
        }
        $orderLog->save();
    }
}
