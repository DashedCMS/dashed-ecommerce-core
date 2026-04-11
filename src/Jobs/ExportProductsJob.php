<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedCore\Jobs\Concerns\CreatesExportRecord;
use Dashed\DashedEcommerceCore\Exports\ProductListExport;
use Dashed\DashedEcommerceCore\Mail\ProductListExportMail;

class ExportProductsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use CreatesExportRecord;

    public $tries = 5;
    public $timeout = 1200;

    public string $email;
    public string $hash;
    public bool $onlyPublicShowable;

    public function __construct(string $email, bool $onlyPublicShowable = false, ?int $userId = null)
    {
        $this->email = $email;
        $this->hash = Str::random();
        $this->onlyPublicShowable = $onlyPublicShowable;

        $this->createExportRecord(
            type: 'products',
            label: 'Producten export',
            parameters: [
                'email' => $email,
                'onlyPublicShowable' => $onlyPublicShowable,
            ],
            userId: $userId,
        );
    }

    public function handle(): void
    {
        try {
            $this->markExportAsProcessing();

            $products = Product::search()->latest();

            if ($this->onlyPublicShowable) {
                $products = $products->publicShowable();
            }

            $products = $products->get();

            $fileName = 'product-list-' . now()->format('Y-m-d-His') . '.xlsx';
            $filePath = 'dashed/exports/' . now()->format('Y/m') . '/' . $this->exportId . '/' . $fileName;

            Excel::store(new ProductListExport($products), $filePath, 'dashed');

            Mail::to($this->email)->send(new ProductListExportMail($this->hash, $filePath));

            $this->markExportAsCompleted($filePath, $fileName);
        } catch (Throwable $e) {
            $this->markExportAsFailed($e);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markExportAsFailed($exception);
    }
}
