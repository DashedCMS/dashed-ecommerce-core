<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Models;

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedEcommerceCore\Database\Factories\PrinterFactory;
use Dashed\DashedEcommerceCore\Enums\PrinterType;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Printer extends Model
{
    use HasApiTokens;
    use HasFactory;

    protected $table = 'dashed__printers';

    protected $guarded = [];

    protected $casts = [
        'type' => PrinterType::class,
        'is_active' => 'bool',
        'max_retries' => 'int',
        'last_ping_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $printer): void {
            if (! $printer->ulid) {
                $printer->ulid = (string) Str::ulid();
            }
        });
    }

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function pendingJobsCount(): int
    {
        return $this->printJobs()->where('status', PrintJobStatus::Pending->value)->count();
    }

    public function isOnline(): bool
    {
        if (! $this->last_ping_at) {
            return false;
        }

        $threshold = (int) Customsetting::get('print_queue.health_check_threshold_seconds', null, 60);

        return $this->last_ping_at->gte(now()->subSeconds($threshold));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    protected static function newFactory(): PrinterFactory
    {
        return new PrinterFactory();
    }
}
