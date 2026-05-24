<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets;

use Filament\Widgets\Widget;
use Dashed\DashedEcommerceCore\Models\Printer;
use Dashed\DashedEcommerceCore\Models\PrintJob;
use Dashed\DashedEcommerceCore\Enums\PrintJobStatus;

class PrintQueueWidget extends Widget
{
    protected static ?int $sort = -5;

    protected string $view = 'dashed-ecommerce-core::filament.widgets.print-queue';

    protected ?string $pollingInterval = '10s';

    public static function canView(): bool
    {
        return Printer::query()->exists();
    }

    protected function getViewData(): array
    {
        return [
            'pendingCount' => PrintJob::query()->where('status', PrintJobStatus::Pending->value)->count(),
            'failedToday' => PrintJob::query()
                ->where('status', PrintJobStatus::Failed->value)
                ->whereDate('failed_at', today())
                ->count(),
            'printers' => Printer::active()
                ->withCount(['printJobs as pending_count' => fn ($q) => $q->where('status', PrintJobStatus::Pending->value)])
                ->get(),
        ];
    }
}
