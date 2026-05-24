<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Enums;

enum PrintJobStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case Printing = 'printing';
    case Done = 'done';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Wacht',
            self::Claimed => 'Geclaimd',
            self::Printing => 'Aan het printen',
            self::Done => 'Klaar',
            self::Failed => 'Mislukt',
            self::Cancelled => 'Geannuleerd',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Claimed => 'info',
            self::Printing => 'warning',
            self::Done => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
