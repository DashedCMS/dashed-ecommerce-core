<?php

namespace Qubiqx\QcommerceEcommerceCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Qubiqx\QcommerceCore\Classes\Mails;
use Qubiqx\QcommerceEcommerceCore\Mail\ProductsWithPastDuePreOrderDateMail;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

class CheckPastDuePreorderDatesForProductsWithoutStockCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qcommerce:checkpastduepreorderdatesforproductswithoutstockcommand';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check past due pre order dates for products without stock';

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
        $products = Product::where('use_stock', 1)->where('out_of_stock_sellable', 1)->where('expected_in_stock_date', '<', now())->where('stock', '<', 1)->get();
        if ($products->count()) {
            if (env('APP_ENV') == 'local') {
                try {
                    Mail::to('robin@qubiqx.com')->send(new ProductsWithPastDuePreOrderDateMail($products));
                } catch (\Exception $e) {
                }
            } else {
                try {
                    foreach (Mails::getAdminNotificationEmails() as $notificationInvoiceEmail) {
                        Mail::to($notificationInvoiceEmail)->send(new ProductsWithPastDuePreOrderDateMail($products));
                    }
                } catch (\Exception $e) {
                }
            }
        }
    }
}
