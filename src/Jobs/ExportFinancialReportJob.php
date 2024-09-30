<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Mail\FinanceExportMail;

class ExportFinancialReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 5;
    public $timeout = 1200;

    public $startDate;
    public $endDate;
    public string $email;
    public string $hash;

    /**
     * Create a new job instance.
     */
    public function __construct($startDate, $endDate, string $email)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->email = $email;
        $this->hash = Str::random();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startDate = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : Order::first()->created_at;
        $endDate = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : Order::latest()->first()->created_at;

        $orders = Order::with(['orderProducts', 'orderProducts.product'])->calculatableForStats();
        if ($startDate) {
            $orders->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $orders->where('created_at', '<=', $endDate);
        }
        $orders = $orders->get();

        $grossRevenue = 0;
        $discounts = 0;
        $returns = 0;
        $netRevenue = 0;
        $taxes = 0;
        $totalRevenue = 0;

        foreach ($orders as $order) {
            if ($order->status == 'return') {
                $returns -= $order->total - $order->btw;
//                $grossRevenue += $order->total;
                $taxes += $order->btw;
            } else {
                $grossRevenue += $order->total - $order->btw;
//                $grossRevenue += $order->total - $order->discount - $order->btw;
                $discounts += $order->discount;
                $netRevenue += $order->total - $order->btw;
                $taxes += $order->btw;
                $totalRevenue += $order->total - $order->discount;
            }
        }

        $totalRevenue -= $returns;

        dd($grossRevenue, $discounts, $returns, $netRevenue, $taxes, $totalRevenue);

        $view = View::make('dashed-ecommerce-core::financial-rapports.financial-report', compact('startDate', 'endDate'));
        $contents = $view->render();
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($contents);
        $output = $pdf->output();

        $pdfPath = '/dashed/tmp-exports/' . $this->hash . '/financial-reports/financial-report.pdf';
        Storage::disk('public')->put($pdfPath, $output);

//        Mail::to($this->email)->send(new FinanceExportMail($this->hash));
        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/' . $this->hash);
    }
}
