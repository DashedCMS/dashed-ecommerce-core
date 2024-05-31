<?php

namespace Dashed\DashedEcommerceCore\Listeners;

use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Dashed\DashedEcommerceCore\Events\Products\ProductUpdatedEvent;

class ClearProductCache
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(ProductUpdatedEvent $event): void
    {
        foreach (Locales::getLocalesArray() as $key => $locale) {
            Cache::forget('product-' . $event->product->id . '-url-' . $key);
        }
        Cache::forget("product-showable-characteristics-" . $event->product->id);
        dd('asdf');
    }
}
