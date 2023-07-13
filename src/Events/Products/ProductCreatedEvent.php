<?php

namespace Qubiqx\QcommerceEcommerceCore\Events\Products;

use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

class ProductCreatedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $product;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
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
