<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Dashed\DashedEcommerceCore\Imports\EANCodesToImport;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Classes\Locales;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\ProductCategory;

class ImportEANCodes implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;
    public $file = '';

    /**
     * Create a new job instance.
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = Storage::disk('local')->path($this->file);
        Excel::import(new EANCodesToImport(), $file);
        Storage::disk('local')->delete($this->file);
    }
}
