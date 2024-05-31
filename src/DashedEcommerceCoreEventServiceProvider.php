<?php

namespace Dashed\DashedEcommerceCore;

use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;
use Dashed\DashedEcommerceCore\Listeners\ClearProductCache;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class DashedEcommerceCoreEventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        ProductSavedEvent::class => [
            ClearProductCache::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
