<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;

class CancelOldOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:canceloldorders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel all old orders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $orders = Order::thisSite()->where('created_at', '<', Carbon::now()->subHours(3))->where('status', 'pending')->get();
        foreach ($orders as $order) {
            $order->changeStatus('cancelled');
        }
    }
}
