<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ExportSpecificPackingSlipsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $hash = time();
        $filePath = '/dashed/orders/packing-slips/packing-slips-' . $hash . '.pdf';

        $pdfMerger = \LynX39\LaraPdfMerger\Facades\PdfMerger::init();

        foreach ($this->orders as $order) {
            $url = $order->downloadPackingSlipUrl();

            if ($url) {
                $packingSlip = Storage::disk('dashed')->get('dashed/packing-slips/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                Storage::disk('public')->put('/dashed/tmp-exports/' . $hash . '/packing-slips-to-export/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf', $packingSlip);
                $packingSlipPath = storage_path('app/public/dashed/tmp-exports/' . $hash . '/packing-slips-to-export/packing-slip-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                $pdfMerger->addPDF($packingSlipPath, 'all');
            }
        }

        $pdfMerger->merge();

        Storage::disk('public')->put($filePath, '');
        $pdfMerger->save(storage_path('app/public' . $filePath));

        Notification::make()
            ->body('Pakbonnen zijn aangemaakt (' . count($this->orders) . ' bestellingen)')
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('download')
                    ->label('Download pakbonnen')
                    ->button()
                    ->url(Storage::disk('public')->url($filePath))
                    ->openUrlInNewTab(),
            ])
            ->success()
            ->sendToDatabase($this->user)
            ->send();
    }
}
