<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Carbon\Carbon;
use Throwable;
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
use Dashed\DashedCore\Jobs\Concerns\CreatesExportRecord;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Mail\FinanceExportMail;

class ExportInvoicesJob implements ShouldQueue
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

    /**
     * Create a new job instance.
     */
    public function __construct($startDate, $endDate, string $sort, string $email, ?int $userId = null)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->sort = $sort;
        $this->email = $email;
        $this->hash = Str::random();

        $this->createExportRecord(
            type: 'invoices',
            label: 'Facturen export',
            parameters: [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'sort' => $sort,
                'email' => $email,
            ],
            userId: $userId,
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
            $this->runExport();
        } catch (Throwable $e) {
            $this->markExportAsFailed($e);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markExportAsFailed($exception);
    }

    protected function runExport(): void
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
            $vatPercentages = [];
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

            $normalZoneTotals = [];
            $ossTotals = [];
            $icpTotals = [];

            foreach ($orders as $order) {
                $subTotal += ($order->total - $order->btw);
                $btw += $order->btw;
                $discount += $order->discount;
                $total += $order->total;

                foreach ($order->vat_percentages ?: [] as $percentage => $amount) {
                    if (! isset($vatPercentages[number_format($percentage, 0)])) {
                        $vatPercentages[number_format($percentage, 0)] = 0;
                    }

                    $vatPercentages[number_format($percentage, 0)] += $amount;
                }

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

                $shippingZone = $order->shippingMethod?->shippingZone;
                if (! $shippingZone) {
                    $shippingZone = ShoppingCart::getShippingZoneByCountry($order->invoice_country ?: $order->country);
                }
                $country = $order->invoice_country ?: $order->country ?: 'Onbekend';
                $zoneName = $shippingZone->name ?? 'Onbekende zone';

                $zoneReverseCharge = (bool) ($shippingZone->vat_reverse_charge ?? false);

                // ICP / verlegd: zone heeft reverse charge aan én klant heeft BTW-nummer
                if ($zoneReverseCharge && ! empty($order->btw_id)) {
                    $icpKey = $country . '|' . $order->btw_id;

                    if (! isset($icpTotals[$icpKey])) {
                        $icpTotals[$icpKey] = [
                            'country' => $country,
                            'vat_number' => $order->btw_id,
                            'revenue' => 0,
                            'zone' => $zoneName,
                        ];
                    }

                    $icpTotals[$icpKey]['revenue'] += ($order->total - $order->btw);

                    continue;
                }

                // Buitenlandse btw / OSS-achtig: zone zonder reverse charge, maar wel buitenlandse btw
                $hasForeignVat = false;
                foreach ($order->vat_percentages ?: [] as $percentage => $amount) {
                    if ((float) $amount > 0 && ! in_array((int) $percentage, [9, 21], true)) {
                        $hasForeignVat = true;
                    }
                }

                if ($hasForeignVat) {
                    $ossKey = $zoneName ?: 'Onbekende zone';

                    if (! isset($ossTotals[$ossKey])) {
                        $ossTotals[$ossKey] = [
                            'zone' => $zoneName ?: 'Onbekende zone',
                            'ex_vat' => 0,
                            'vat' => 0,
                            'incl_vat' => 0,
                        ];
                    }

                    $ossTotals[$ossKey]['ex_vat'] += ($order->total - $order->btw);
                    $ossTotals[$ossKey]['vat'] += $order->btw;
                    $ossTotals[$ossKey]['incl_vat'] += $order->total;

                    continue;
                }

                // Normale zones alleen per verzendzone
                $normalKey = $zoneName ?: 'Onbekende zone';

                if (! isset($normalZoneTotals[$normalKey])) {
                    $normalZoneTotals[$normalKey] = [
                        'zone' => $zoneName ?: 'Onbekende zone',
                        'ex_vat' => 0,
                        'vat' => 0,
                        'incl_vat' => 0,
                    ];
                }

                $normalZoneTotals[$normalKey]['ex_vat'] += ($order->total - $order->btw);
                $normalZoneTotals[$normalKey]['vat'] += $order->btw;
                $normalZoneTotals[$normalKey]['incl_vat'] += $order->total;
            }

            $normalZoneTotals = collect($normalZoneTotals)
                ->sortBy('zone')
                ->values()
                ->all();

            $ossTotals = collect($ossTotals)
                ->sortBy('zone')
                ->values()
                ->all();

            $icpTotals = collect($icpTotals)->sortBy([
                ['country', 'asc'],
                ['vat_number', 'asc'],
            ])->values()->all();

            $view = View::make('dashed-ecommerce-core::invoices.combined-invoices', compact(
                'subTotal',
                'btw',
                'vatPercentages',
                'paymentCosts',
                'shippingCosts',
                'discount',
                'total',
                'productSales',
                'startDate',
                'endDate',
                'normalZoneTotals',
                'ossTotals',
                'icpTotals',
            ));

            $contents = $view->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($contents);
            $output = $pdf->output();

            $invoicePath = '/dashed/tmp-exports/' . $this->hash . '/invoices/exported-invoice.pdf';
            Storage::disk('public')->put($invoicePath, $output);
        }

        // Move the generated file to the permanent exports location
        $fileName = 'invoices-' . now()->format('Y-m-d-His') . '.pdf';
        $finalRelativePath = 'dashed/exports/' . now()->format('Y/m') . '/' . $this->exportId . '/' . $fileName;
        $sourceRelativePath = ltrim($invoicePath, '/');

        if (Storage::disk('public')->exists($sourceRelativePath)) {
            $contents = Storage::disk('public')->get($sourceRelativePath);
            Storage::disk('public')->put($finalRelativePath, $contents);
            Storage::disk('public')->delete($sourceRelativePath);
        }

        Mail::to($this->email)->send(new FinanceExportMail(
            $this->hash,
            'Facturen van ' . ($startDate ? $startDate->format('d-m-Y') : 'het begin') . ' tot ' . ($endDate ? $endDate->format('d-m-Y') : 'nu'),
            $finalRelativePath,
        ));

        $this->markExportAsCompleted($finalRelativePath, $fileName);
    }
}
