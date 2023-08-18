<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Exports\OrderListExport;
use Dashed\DashedEcommerceCore\Exports\OrderListPerInvoiceLineExport;
use Dashed\DashedEcommerceCore\Exports\ProductListExport;
use Dashed\DashedEcommerceCore\Mail\FinanceExportMail;
use Dashed\DashedEcommerceCore\Mail\OrderListExportMail;
use Dashed\DashedEcommerceCore\Mail\ProductListExportMail;
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

class ExportProductsJob implements ShouldQueue
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
    public function __construct(string $email)
    {
        $this->email = $email;
        $this->hash = Str::random();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $products = Product::notParentProduct()
            ->search()
            ->latest()
            ->get();

        Excel::store(new ProductListExport($products), '/dashed/tmp-exports/' . $this->hash . '/product-lists/product-list.xlsx', 'public');

        Mail::to($this->email)->send(new ProductListExportMail($this->hash));

        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/' . $this->hash);
    }
}
