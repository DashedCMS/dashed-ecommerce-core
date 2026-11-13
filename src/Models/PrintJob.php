<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedEcommerceCore\Enums\PrintJobType;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Dashed\DashedEcommerceCore\Database\Factories\PrintJobFactory;

class PrintJob extends Model
{
    use HasFactory;

    protected $table = 'dashed__print_jobs';

    protected $guarded = [];

    protected $casts = [
        'type' => PrintJobType::class,
        'status' => PrintJobStatus::class,
        'attempts' => 'int',
        'claimed_at' => 'datetime',
        'printed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $job): void {
            if (! $job->ulid) {
                $job->ulid = (string) Str::ulid();
            }

            // Wie en via welk kanaal, voor het orderlogboek. De aanmaakplek mag
            // het kanaal zelf meegeven; anders afleiden uit de context.
            if (! self::hasLogColumns()) {
                // Migratie nog niet gedraaid (bv. vlak na een deploy): niet
                // in kolommen schrijven die er nog niet zijn.
                unset($job->user_id, $job->channel);

                return;
            }
            if (! $job->channel) {
                $job->channel = self::detectChannel();
            }
            // Automatisch klaargezette opdrachten zijn van het systeem, ook als
            // er toevallig iemand is ingelogd (bv. een listener bij betaald).
            if (! $job->user_id && $job->channel !== self::CHANNEL_AUTOMATIC) {
                $job->user_id = OrderLog::currentUserId();
            }
        });

        static::created(function (self $job): void {
            if (! $job->order_id) {
                return;
            }

            rescue(fn () => OrderLog::createLog(
                orderId: (int) $job->order_id,
                tag: 'order.print.queued',
                note: OrderLog::printNote($job->documentLabel(), $job->printerName(), self::channelLabel($job->channel ?? self::detectChannel())),
                isSystem: $job->getAttribute('user_id') ? null : true,
                userId: $job->getAttribute('user_id') ? (int) $job->getAttribute('user_id') : null,
            ), report: true);
        });
    }

    private static ?bool $hasLogColumns = null;

    /** Bestaan user_id en channel al (migratie 2026_11_13_100000)? */
    public static function hasLogColumns(): bool
    {
        return self::$hasLogColumns ??= rescue(
            fn () => \Illuminate\Support\Facades\Schema::hasColumns('dashed__print_jobs', ['user_id', 'channel']),
            false,
            false,
        );
    }

    public const CHANNEL_CMS = 'cms';

    public const CHANNEL_APP = 'app';

    public const CHANNEL_AUTOMATIC = 'automatisch';

    public const CHANNEL_POS = 'kassa';

    /**
     * Kanaal uit de context: een API-verzoek is de app, een ingelogde
     * gebruiker op een gewone pagina het CMS, al het andere (wachtrij,
     * console, listeners zonder gebruiker) automatisch.
     */
    public static function detectChannel(): string
    {
        if (! app()->runningInConsole() && app()->bound('request')) {
            $request = request();
            if ($request->is('api/v1/point-of-sale/*')) {
                return self::CHANNEL_POS;
            }
            if ($request->is('api/*')) {
                return self::CHANNEL_APP;
            }

            if (OrderLog::currentUserId()) {
                return self::CHANNEL_CMS;
            }
        }

        return self::CHANNEL_AUTOMATIC;
    }

    public static function channelLabel(?string $channel): ?string
    {
        return match ($channel) {
            self::CHANNEL_CMS => 'CMS',
            self::CHANNEL_APP => 'app',
            self::CHANNEL_AUTOMATIC => 'automatisch',
            self::CHANNEL_POS => 'kassa',
            null, '' => null,
            default => $channel,
        };
    }

    /** Documentsoort in het Nederlands (verzendlabel, pakbon, factuur). */
    public function documentLabel(): string
    {
        return $this->type instanceof PrintJobType ? $this->type->label() : (string) $this->type;
    }

    public function printerName(): ?string
    {
        if (! $this->printer_id) {
            return null;
        }

        $printer = $this->relationLoaded('printer') ? $this->printer : Printer::find($this->printer_id);

        return $printer?->name ?: null;
    }

    /**
     * Systeemregel in het orderlogboek bij klaar of mislukt. Nooit laten
     * falen: de printer-worker moet zijn status altijd kwijt kunnen.
     */
    private function logResult(string $tag, ?string $error = null): void
    {
        if (! $this->order_id) {
            return;
        }

        rescue(fn () => OrderLog::createLog(
            orderId: (int) $this->order_id,
            tag: $tag,
            note: OrderLog::printNote($this->documentLabel(), $this->printerName(), null, $error),
            isSystem: true,
        ), report: true);
    }

    /**
     * Een handmatige actie op de printopdracht (opnieuw, annuleren) in het
     * orderlogboek, door de ingelogde gebruiker.
     */
    public function logManualAction(string $tag): void
    {
        if (! $this->order_id) {
            return;
        }

        rescue(fn () => OrderLog::createLog(
            orderId: (int) $this->order_id,
            tag: $tag,
            note: OrderLog::printNote($this->documentLabel(), $this->printerName()),
        ), report: true);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(Printer::class);
    }

    public function printable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->where('status', PrintJobStatus::Pending->value);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', PrintJobStatus::Failed->value);
    }

    public function scopeForPrinter($query, int $printerId)
    {
        return $query->where('printer_id', $printerId);
    }

    public function markAsClaimed(Printer $printer): void
    {
        $this->update([
            'status' => PrintJobStatus::Claimed,
            'printer_id' => $printer->id,
            'claimed_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markAsDone(): void
    {
        $this->update([
            'status' => PrintJobStatus::Done,
            'printed_at' => now(),
        ]);

        $this->logResult('order.print.done');

        if ($this->type !== PrintJobType::ShippingLabel) {
            return;
        }

        // Een specifiek label geprint → enkel die rij uit de wachtrij halen.
        if ($this->printable_type && $this->printable_id && class_exists($this->printable_type)) {
            $printable = ($this->printable_type)::find($this->printable_id);
            $printable?->update(['label_printed' => true]);

            return;
        }

        // Geen specifiek label gekoppeld (order-niveau print via de app) → alle
        // nog-niet-geprinte verzendlabels van deze order uit de wachtrij halen.
        $this->markOrderShippingLabelsPrinted();
    }

    /**
     * Zet label_printed=1 op alle nog-niet-geprinte vervoerder-labels van deze
     * order, zodat ze uit de "Download labels"-wachtrij verdwijnen.
     */
    private function markOrderShippingLabelsPrinted(): void
    {
        foreach (self::shippingLabelSources() as $sourceModel) {
            $sourceModel::query()
                ->where('order_id', $this->order_id)
                ->where('label_printed', 0)
                ->update(['label_printed' => 1]);
        }
    }

    /** @return array<int, class-string> */
    public static function shippingLabelSources(): array
    {
        return array_values(array_filter([
            class_exists(\Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class)
                ? \Dashed\DashedEcommerceMyParcel\Models\MyParcelOrder::class
                : null,
            class_exists(\Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class)
                ? \Dashed\DashedEcommerceVeloyd\Models\VeloydOrder::class
                : null,
        ]));
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => PrintJobStatus::Failed,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);

        $this->logResult('order.print.failed', $errorMessage);
    }

    public function retry(): void
    {
        $this->update([
            'status' => PrintJobStatus::Pending,
            'printer_id' => null,
            'error_message' => null,
            'claimed_at' => null,
            'failed_at' => null,
        ]);
    }

    /** Opnieuw in de wachtrij door een gebruiker (CMS of app), met orderlog. */
    public function retryByUser(): void
    {
        $this->retry();
        $this->logManualAction('order.print.retried');
    }

    /** Geannuleerd door een gebruiker (CMS of app), met orderlog. */
    public function cancelByUser(): void
    {
        $this->update(['status' => PrintJobStatus::Cancelled]);
        $this->logManualAction('order.print.cancelled');
    }

    protected static function newFactory(): PrintJobFactory
    {
        return new PrintJobFactory();
    }
}
