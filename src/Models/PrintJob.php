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
        });
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

    protected static function newFactory(): PrintJobFactory
    {
        return new PrintJobFactory();
    }
}
