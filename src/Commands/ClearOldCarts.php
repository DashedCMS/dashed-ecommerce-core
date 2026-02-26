<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Cart;

class ClearOldCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:clear-old-carts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear old carts';

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

        foreach (Cart::where('updated_at', '<', Carbon::now()->subMonth())->get() as $cart) {
            $cart->delete();
        }
    }
}
