<?php

namespace Dashed\DashedEcommerceCore;

use Dashed\DashedEcommerceCore\Listeners\ClearProductCache;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;
use Dashed\DashedEcommerceCore\Listeners\UpdateOrderReservedStock;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Listeners\PrintDocumentsAfterPaidOrder;
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
        OrderMarkedAsPaidEvent::class => [
          PrintDocumentsAfterPaidOrder::class,
        ],
        OrderCreatedEvent::class => [
          UpdateOrderReservedStock::class,
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
