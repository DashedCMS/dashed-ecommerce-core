<?php

namespace Dashed\DashedEcommerceCore;

use Dashed\DashedEcommerceCore\Listeners\ClearProductCache;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCreatedEvent;
use Dashed\DashedEcommerceCore\Events\Orders\OrderCancelledEvent;
use Dashed\DashedEcommerceCore\Events\Products\ProductSavedEvent;
use Dashed\DashedEcommerceCore\Listeners\UpdateOrderReservedStock;
use Dashed\DashedEcommerceCore\Events\Orders\OrderMarkedAsPaidEvent;
use Dashed\DashedEcommerceCore\Listeners\PrintDocumentsAfterPaidOrder;
use Dashed\DashedEcommerceCore\Listeners\SendLatePaidAdminNotification;
use Dashed\DashedEcommerceCore\Events\Orders\OrderFulfillmentStatusChangedEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Dashed\DashedEcommerceCore\Listeners\OrderHandledFlow\QueueOrderFlowEmailsListener;
use Dashed\DashedEcommerceCore\Listeners\AbandonedCart\CancelPendingAbandonedEmailsListener;
use Dashed\DashedEcommerceCore\Listeners\AbandonedCart\QueueAbandonedCartEmailsForOrderListener;

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
          CancelPendingAbandonedEmailsListener::class,
          SendLatePaidAdminNotification::class,
        ],
        OrderCreatedEvent::class => [
          UpdateOrderReservedStock::class,
        ],
        OrderCancelledEvent::class => [
            QueueAbandonedCartEmailsForOrderListener::class,
        ],
        OrderFulfillmentStatusChangedEvent::class => [
            QueueOrderFlowEmailsListener::class,
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
