<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Exports\OrderListExport;
use Dashed\DashedCore\Jobs\Concerns\CreatesExportRecord;
use Dashed\DashedEcommerceCore\Mail\OrderListExportMail;
use Dashed\DashedEcommerceCore\Exports\OrderListPerInvoiceLineExport;

class ExportOrdersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use CreatesExportRecord;

    public $tries = 5;
    public $timeout = 1200;

    public $startDate;
    public $endDate;
    public string $sort;
    public string $email;
    public string $hash;

    public function __construct($startDate, $endDate, string $sort, string $email, ?int $userId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->sort = $sort;
        $this->email = $email;
        $this->hash = Str::random();

        $this->createExportRecord(
            type: 'orders',
            label: 'Bestellingen export',
            parameters: [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'sort' => $sort,
                'email' => $email,
            ],
            userId: $userId,
        );
    }

    public function handle(): void
    {
        try {
            $this->markExportAsProcessing();

            $orders = Order::isPaidOrReturn();

            if ($this->startDate) {
                $orders->where('created_at', '>=', Carbon::parse($this->startDate)->startOfDay());
            }

            if ($this->endDate) {
                $orders->where('created_at', '<=', Carbon::parse($this->endDate)->endOfDay());
            }

            $orders = $orders->get();

            $fileName = 'order-list-' . now()->format('Y-m-d-His') . '.xlsx';
            $filePath = 'dashed/exports/' . now()->format('Y/m') . '/' . $this->exportId . '/' . $fileName;

            if ($this->sort == 'normal') {
                Excel::store(new OrderListExport($orders), $filePath, 'dashed');
            } elseif ($this->sort == 'perInvoiceLine') {
                Excel::store(new OrderListPerInvoiceLineExport($orders), $filePath, 'dashed');
            }

            AdminNotifier::send(new OrderListExportMail($this->hash, $filePath), $this->email);

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
