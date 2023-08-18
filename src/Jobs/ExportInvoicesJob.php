<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Carbon\Carbon;
use Dashed\DashedEcommerceCore\Mail\FinanceExportMail;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class ExportInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
    public function __construct($startDate, $endDate, string $sort, string $email)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->sort = $sort;
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

        $orders = Order::with(['orderProducts', 'orderProducts.product'])->where('order_origin', 'own')->calculatableForStats();
        if ($startDate) {
            $orders->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $orders->where('created_at', '<=', $endDate);
        }
        $orders = $orders->get();

        if ($this->sort == 'merged') {
            $pdfMerger = \LynX39\LaraPdfMerger\Facades\PdfMerger::init();

            foreach ($orders as $order) {
                $url = $order->downloadInvoiceUrl();
                if ($url) {
                    $invoice = Storage::disk('dashed')->get('dashed/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                    Storage::disk('public')->put('/dashed/tmp-exports/' . $this->hash . '/invoices-to-export/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf', $invoice);
                    $invoicePath = storage_path('app/public/dashed/tmp-exports/' . $this->hash . '/invoices-to-export/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                    $pdfMerger->addPDF($invoicePath, 'all');
                }
            }

            $pdfMerger->merge();

            $invoicePath = '/dashed/tmp-exports/' . $this->hash . '/invoices/exported-invoice.pdf';
            Storage::disk('public')->put($invoicePath, '');
            $pdfMerger->save(storage_path('app/public' . $invoicePath));
        } elseif ($this->sort == 'combined') {
            $subTotal = 0;
            $btw = 0;
            $paymentCosts = 0;
            $shippingCosts = 0;
            $discount = 0;
            $total = 0;

            $products = Product::withTrashed()->get();
            $productSales = [];

            foreach ($products as $product) {
                $productSales[$product->id] = [
                    'name' => $product->name,
                    'quantity' => 0,
                    'totalPrice' => 0,
                ];
            }

            foreach ($orders as $order) {
                $subTotal += $order->subtotal;
                $btw += $order->btw;
                $discount += $order->discount;
                $total += $order->total;

                foreach ($order->orderProducts as $orderProduct) {
                    if ($orderProduct->product) {
                        $productSales[$orderProduct->product->id] = [
                            'name' => $productSales[$orderProduct->product->id]['name'],
                            'quantity' => $productSales[$orderProduct->product->id]['quantity'] + $orderProduct->quantity,
                            'totalPrice' => $productSales[$orderProduct->product->id]['totalPrice'] + $orderProduct->price,
                        ];
                    } elseif ($orderProduct->sku) {
                        $productSales[$orderProduct->sku] = [
                            'name' => $productSales[$orderProduct->sku]['name'] ?? $orderProduct->name,
                            'quantity' => ($productSales[$orderProduct->sku]['quantity'] ?? 0) + $orderProduct->quantity,
                            'totalPrice' => ($productSales[$orderProduct->sku]['totalPrice'] ?? 0) + $orderProduct->price,
                        ];
                    } else {
                        $productSales['noproduct' . $orderProduct->id] = [
                            'name' => $orderProduct->name,
                            'quantity' => $orderProduct->quantity,
                            'totalPrice' => $orderProduct->price,
                        ];
                    }
                }
            }

            $view = View::make('dashed-ecommerce-core::invoices.combined-invoices', compact('subTotal', 'btw', 'paymentCosts', 'shippingCosts', 'discount', 'total', 'productSales', 'startDate', 'endDate'));
            $contents = $view->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($contents);
            $output = $pdf->output();

            $invoicePath = '/dashed/tmp-exports/' . $this->hash . '/invoices/exported-invoice.pdf';
            Storage::disk('public')->put($invoicePath, $output);
        }

        Mail::to($this->email)->send(new FinanceExportMail($this->hash));
        Storage::disk('public')->deleteDirectory('/dashed/tmp-exports/' . $this->hash);
    }
}
