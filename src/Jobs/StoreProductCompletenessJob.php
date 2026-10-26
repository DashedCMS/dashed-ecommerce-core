<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Classes\ProductCompleteness;

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

    public $tries = 1;

    public $timeout = 1200;

    public function handle(): void
    {
        ProductCompleteness::storeAll();
    }
}
