<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Support\BackgroundTasks;
use Dashed\DashedEcommerceCore\Imports\EANCodesToImport;

class ImportEANCodes implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TASK_TYPE = 'ean-import';

    public $tries = 5;
    public $timeout = 1200;
    public $file = '';

    /**
     * De beheerder die de import startte, voor het takenicoon.
     */
    public ?int $startedByUserId = null;

    /**
     * Create a new job instance.
     */
    public function __construct($file, ?int $startedByUserId = null)
    {
        $this->file = $file;
        $this->startedByUserId = $startedByUserId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $file = Storage::disk('local')->path($this->file);
        $task = BackgroundTasks::start(self::TASK_TYPE, __('EAN-codes importeren'), null, $this->startedByUserId, $this->job?->uuid());

        Excel::import(new EANCodesToImport(), $file);
        Storage::disk('local')->delete($this->file);

        $task?->finish(__('Het bestand is verwerkt.'));
    }

    public function failed(Throwable $e): void
    {
        BackgroundTasks::fail(self::TASK_TYPE, $e, $this->job?->uuid(), $this->startedByUserId);
    }
}
