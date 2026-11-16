<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Support\BackgroundTasks;
use Dashed\DashedEcommerceCore\Classes\ProductCompleteness;
use Dashed\DashedEcommerceCore\Filament\Pages\ProductCompletenessPage;

/**
 * Rekent de volledigheid van de hele catalogus opnieuw uit (zie
 * ProductCompleteness::storeAll()). Elke nacht, want een foto op de
 * productgroep of een nieuwe categoriekoppeling raakt het product zelf niet.
 */
class StoreProductCompletenessJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TASK_TYPE = 'product-completeness';

    public $tries = 1;

    public $timeout = 1200;

    public function handle(): void
    {
        $task = BackgroundTasks::start(self::TASK_TYPE, __('Volledigheid van producten berekenen'));

        $count = ProductCompleteness::storeAll();

        $task?->finish(
            __(':aantal producten bijgewerkt.', ['aantal' => $count]),
            rescue(fn () => ProductCompletenessPage::getUrl(), null, false),
            __('Bekijken'),
        );
    }

    public function failed(Throwable $e): void
    {
        BackgroundTasks::fail(self::TASK_TYPE, $e);
    }
}
