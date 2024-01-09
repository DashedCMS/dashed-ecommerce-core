<?php

namespace Dashed\DashedEcommerceCore\Commands;

use Illuminate\Console\Command;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Jobs\SendInvoiceJob;

class SendInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dashed:send-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send invoices';

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
        $orders = Order::thisSite()->where('status', 'paid')->where('invoice_send_to_customer', 0)->get();
        foreach ($orders as $order) {
            SendInvoiceJob::dispatch($order, null);
        }
    }
}
