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
        // Delete empty carts older than 1 day. An empty cart with a discount
        // code is kept (a popup applies its welcome code before anything is in
        // the cart); it falls under the one-month rule below instead.
        Cart::whereDoesntHave('items')
            ->whereNull('discount_code_id')
            ->where('updated_at', '<', Carbon::now()->subDay())
            ->delete();

        // Delete all carts (with items) older than 1 month
        foreach (Cart::where('updated_at', '<', Carbon::now()->subMonth())->get() as $cart) {
            $cart->items()->delete();
            $cart->delete();
        }
    }
}
