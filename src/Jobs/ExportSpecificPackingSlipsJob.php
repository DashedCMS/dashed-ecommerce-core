<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedCore\Jobs\Concerns\CreatesExportRecord;

class ExportSpecificPackingSlipsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use CreatesExportRecord;

    public $tries = 5;
    public $timeout = 1200;

    public $orders;
    public User $user;

    /**
     * Create a new job instance.
     */
    public function __construct($orders, User $user)
    {
        $this->orders = $orders;
        $this->user = $user;

        $this->createExportRecord(
            type: 'packing_slips',
            label: 'Pakbonnen export',
            parameters: [
                'orders_count' => is_countable($orders) ? count($orders) : 0,
            ],
            userId: $user->id,
            disk: 'public',
        );
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->markExportAsProcessing();

            $fileName = 'packing-slips-' . now()->format('Y-m-d-His') . '.pdf';
            $filePath = 'dashed/exports/' . now()->format('Y/m') . '/' . $this->exportId . '/' . $fileName;

            $pdfMerger = \LynX39\LaraPdfMerger\Facades\PdfMerger::init();

            foreach ($this->orders as $order) {
                $url = $order->downloadPackingSlipUrl();

                if ($url) {
                    $packingSlip = Storage::disk('dashed')->get('dashed/packing-slips/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                    $tmpRelative = 'dashed/tmp-exports/' . $this->exportId . '/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf';
                    Storage::disk('public')->put($tmpRelative, $packingSlip);
                    $pdfMerger->addPDF(storage_path('app/public/' . $tmpRelative), 'all');
                }
            }

            $pdfMerger->merge();

            Storage::disk('public')->put($filePath, '');
            $pdfMerger->save(storage_path('app/public/' . $filePath));

            // Cleanup tmp files
            Storage::disk('public')->deleteDirectory('dashed/tmp-exports/' . $this->exportId);

            $this->markExportAsCompleted($filePath, $fileName);

            Notification::make()
                ->body('Pakbonnen zijn aangemaakt (' . count($this->orders) . ' bestellingen)')
                ->persistent()
                ->actions([
                    Action::make('download')
                        ->label('Download pakbonnen')
                        ->button()
                        ->url(Storage::disk('public')->url($filePath))
                        ->openUrlInNewTab(),
                ])
                ->success()
                ->sendToDatabase($this->user)
                ->send();
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
