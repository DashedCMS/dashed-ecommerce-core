<?php

namespace Dashed\DashedEcommerceCore\Events\Products;

use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ProductInformationUpdatedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public ProductGroup $productGroup;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(ProductGroup $productGroup)
    {
        $this->productGroup = $productGroup;
    }

    //    /**
    //     * Get the channels the event should broadcast on.
    //     *
    //     * @return \Illuminate\Broadcasting\Channel|array
    //     */
    //    public function broadcastOn()
    //    {
    //        return new PrivateChannel('channel-name');
    //    }
}
