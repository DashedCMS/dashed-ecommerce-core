<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use App\Models\User;
use Dashed\DashedEcommerceCore\Imports\PricePerProductForUserImport;
use Dashed\DashedEcommerceCore\Imports\ProductsToEditImport;
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

class ImportPricesPerUserPerProduct implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;
    public string $file;
    public User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, string $file)
    {
        $this->user = $user;
        $this->file = $file;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = Storage::disk('local')->path($this->file);
        Excel::import(new PricePerProductForUserImport($this->user), $file);
        Storage::disk('local')->delete($this->file);
    }
}
