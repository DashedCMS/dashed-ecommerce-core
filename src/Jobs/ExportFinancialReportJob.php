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
use Dashed\DashedEcommerceCore\Mail\FinanceReportMail;

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

        $orders = Order::with(['orderProducts', 'orderProducts.product'])->isPaidOrReturn();
        if ($startDate) {
            $orders->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $orders->where('created_at', '<=', $endDate);
        }
        $orders = $orders->get();

        $vatPercentages = [];
        $transactions = [];
        $grossRevenue = 0;
        $discounts = 0;
        $returns = 0;
        $netRevenue = 0;
        $taxes = 0;
        $totalRevenue = 0;

        foreach ($orders as $order) {
            if ($order->status == 'return') {
                $returns -= $order->total - $order->btw;
                $netRevenue += $order->total - $order->btw;
                $taxes += $order->btw;
                $totalRevenue += $order->btw;
            } else {
                $grossRevenue += $order->total - $order->btw + $order->discountWithoutTax;
                $discounts += $order->discountWithoutTax;
                $netRevenue += $order->total - $order->btw;
                $taxes += $order->btw;
                $totalRevenue += $order->total;
            }

            foreach ($order->vat_percentages ?: [] as $vatPercentage => $amount) {
                if (! isset($vatPercentages[number_format($vatPercentage, 0)])) {
                    $vatPercentages[number_format($vatPercentage, 0)] = 0;
                }

                $vatPercentages[number_format($vatPercentage, 0)] += $amount;
            }

            $firstPayment = $order->orderPayments()->first();
            if (! isset($transactions[$firstPayment->payment_method_id ?? 'unknown'])) {
                $transactions[$firstPayment->payment_method_id ?? 'unknown'] = [
                    'name' => $firstPayment->paymentMethod->name ?? 'unknown',
                    'amount' => 0,
                    'transactions' => 0,
                ];
            }
            $transactions[$firstPayment->payment_method_id ?? 'unknown']['amount'] += $order->total;
            $transactions[$firstPayment->payment_method_id ?? 'unknown']['transactions']++;
        }

        $totalRevenue -= $returns;

        $view = View::make('dashed-ecommerce-core::financial-reports.financial-report', compact('startDate', 'endDate', 'grossRevenue', 'discounts', 'returns', 'netRevenue', 'taxes', 'totalRevenue', 'vatPercentages', 'transactions'));
        $contents = $view->render();
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($contents);
        $output = $pdf->output();

        $pdfPath = '/dashed/tmp-exports/' . $this->hash . '/financial-reports/financial-report.pdf';
        Storage::disk('public')->put($pdfPath, $output);

        Mail::to($this->email)->send(new FinanceReportMail($this->hash, 'Financieel rapport van ' . $startDate->format('Y-m-d') . ' tot ' . $endDate->format('Y-m-d')));
        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/' . $this->hash);
    }
}
