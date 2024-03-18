<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Exports\ProductListExport;
use Dashed\DashedEcommerceCore\Mail\ProductListExportMail;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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

        Excel::store(new ProductListExport($products), '/dashed/tmp-exports/' . $this->hash . '/product-lists/product-list.xlsx', 'dashed');

        Mail::to($this->email)->send(new ProductListExportMail($this->hash));

        Storage::disk('dashed')->deleteDirectory('/dashed/tmp-exports/' . $this->hash);
    }
}
