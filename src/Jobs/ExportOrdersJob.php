<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Exports\OrderListExport;
use Dashed\DashedEcommerceCore\Exports\OrderListPerInvoiceLineExport;
use Dashed\DashedEcommerceCore\Mail\FinanceExportMail;
use Dashed\DashedEcommerceCore\Mail\OrderListExportMail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public $startDate;
    public $endDate;
    public string $sort;
    public string $email;
    public string $hash;

    /**
     * Create a new job instance.
     */
    public function __construct($startDate, $endDate, string $sort, string $email)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->sort = $sort;
        $this->email = $email;
        $this->hash = Str::random();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $orders = Order::isPaidOrReturn();

        if ($this->startDate) {
            $orders->where('created_at', '>=', Carbon::parse($this->startDate)->startOfDay());
        }

        if ($this->endDate) {
            $orders->where('created_at', '<=', Carbon::parse($this->endDate)->endOfDay());
        }

        $orders = $orders->get();

        if ($this->sort == 'normal') {
            Excel::store(new OrderListExport($orders), '/dashed/tmp-exports/' . $this->hash . '/order-lists/order-list.xlsx', 'public');
        } elseif ($this->sort == 'perInvoiceLine') {
            Excel::store(new OrderListPerInvoiceLineExport($orders), '/dashed/tmp-exports/' . $this->hash . '/order-lists/order-list.xlsx', 'public');
        }

        Mail::to($this->email)->send(new OrderListExportMail($this->hash));

        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/' . $this->hash);
    }
}
