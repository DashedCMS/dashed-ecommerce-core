<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\Dashed\Models\Order;
use Dashed\Dashed\Models\Product;

class RecalculatePurchasesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:recalculate-purchases';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate purchases';

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
        $products = Product::get();
        foreach ($products as $product) {
            $this->info('Setting product ' . $product->name . ' to 0 purchases');
            $product->purchases = 0;
            $product->save();
        }

        $orders = Order::with(['orderProducts'])->isPaidOrReturn()->get();
        foreach ($orders as $order) {
            $this->info('Calculating order ' . $order->id);
            foreach ($order->orderProducts as $orderProduct) {
                $product = Product::find($orderProduct->product_id);
                if ($product) {
                    $product->purchases += $orderProduct->quantity;
                    $product->save();
                }
            }
        }
    }
}
