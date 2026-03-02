<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;

class DeleteOldCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:delete-old-carts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete old carts';

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
        foreach(Order::where('type', 'cart')->cursor() as $cart) {
            if ($cart->updated_at->lt(Carbon::now()->subDays(30))) {
                $cart->delete();
            }
        }
    }
}
