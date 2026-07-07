<?php

namespace Dashed\DashedEcommerceCore\Jobs;

use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedCore\Notifications\AdminNotifier;
use Dashed\DashedEcommerceCore\Classes\ShoppingCart;
use Dashed\DashedEcommerceCore\Mail\FinanceExportMail;
use Dashed\DashedCore\Jobs\Concerns\CreatesExportRecord;
use Dashed\DashedCore\Jobs\Concerns\HandlesQueueFailures;

class ExportInvoicesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use HandlesQueueFailures;
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
        $this->reportFailure($exception);
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

            // De btw-uitsplitsing wordt afgeleid uit de inclusieve bedragen per
            // tarief: btw = round(incl * tarief / (100 + tarief)), ex = incl - btw.
            // Zo komt elke regel exact op het tarief uit (ex * tarief == btw) én
            // blijft de inclusieve kolom gelijk aan de daadwerkelijk ontvangen omzet.
            // Het per-order optellen van (per factuur afgeronde) btw zou anders enkele
            // centen wegdriften t.o.v. de btw over het periodetotaal.
            // Inclusieve omzet per btw-tarief o.b.v. de orderregels (elke regel heeft
            // een eigen vat_rate). Zo tellen 0%-regels (bv. margeregeling) niet mee in
            // de 21%-grondslag. Het regeltotaal wordt geschaald naar order->total zodat
            // lump-kortingen evenredig over de tarieven worden verdeeld en de som gelijk
            // blijft aan de daadwerkelijk ontvangen omzet.
            $inclPerRateForOrder = function ($order): array {
                $perRate = [];
                $sumLines = 0.0;
                foreach ($order->orderProducts as $orderProduct) {
                    $rate = (int) round((float) ($orderProduct->vat_rate ?? 21));
                    $perRate[$rate] = ($perRate[$rate] ?? 0.0) + (float) $orderProduct->price;
                    $sumLines += (float) $orderProduct->price;
                }

                $totalIncl = (float) $order->total;

                if ($sumLines <= 0.0) {
                    // Geen regels om op te splitsen: val terug op het enige btw-tarief
                    // van de order, of op 0% (geen btw) als er geen btw geboekt is.
                    $rates = [];
                    foreach ($order->vat_percentages ?: [] as $rate => $amount) {
                        if ((float) $amount != 0.0) {
                            $rates[(int) $rate] = true;
                        }
                    }

                    if ($totalIncl == 0.0 || count($rates) === 0) {
                        return [];
                    }

                    return [array_key_first($rates) => $totalIncl];
                }

                // Schaal de regelbedragen naar het werkelijke ordertotaal (lump-korting
                // of kleine afwijking); rest naar het laatste tarief zodat de som exact
                // gelijk blijft aan order->total.
                if (abs($sumLines - $totalIncl) >= 0.01) {
                    $factor = $totalIncl / $sumLines;
                    $assigned = 0.0;
                    $rateKeys = array_keys($perRate);
                    $lastRate = end($rateKeys);
                    foreach ($perRate as $rate => $amount) {
                        if ($rate === $lastRate) {
                            $perRate[$rate] = round($totalIncl - $assigned, 2);
                        } else {
                            $scaled = round($amount * $factor, 2);
                            $perRate[$rate] = $scaled;
                            $assigned += $scaled;
                        }
                    }
                }

                return array_filter($perRate, fn ($amount) => abs($amount) >= 0.005);
            };

            $vatFromIncl = function (float $incl, int $rate): float {
                return round($incl * $rate / (100 + $rate), 2);
            };

            $globalInclPerRate = [];

            foreach ($orders as $order) {
                $discount += $order->discount;
                $total += $order->total;

                $orderInclPerRate = $inclPerRateForOrder($order);

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

                // Omzet mét btw (binnenlands of OSS) telt mee voor de globale uitsplitsing.
                foreach ($orderInclPerRate as $rate => $incl) {
                    $globalInclPerRate[$rate] = ($globalInclPerRate[$rate] ?? 0) + $incl;
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
                            'incl_vat' => 0,
                            'rates' => [],
                        ];
                    }

                    $ossTotals[$ossKey]['incl_vat'] += $order->total;
                    foreach ($orderInclPerRate as $rate => $incl) {
                        $ossTotals[$ossKey]['rates'][$rate] = ($ossTotals[$ossKey]['rates'][$rate] ?? 0) + $incl;
                    }

                    continue;
                }

                // Normale zones alleen per verzendzone
                $normalKey = $zoneName ?: 'Onbekende zone';

                if (! isset($normalZoneTotals[$normalKey])) {
                    $normalZoneTotals[$normalKey] = [
                        'zone' => $zoneName ?: 'Onbekende zone',
                        'incl_vat' => 0,
                        'rates' => [],
                    ];
                }

                $normalZoneTotals[$normalKey]['incl_vat'] += $order->total;
                foreach ($orderInclPerRate as $rate => $incl) {
                    $normalZoneTotals[$normalKey]['rates'][$rate] = ($normalZoneTotals[$normalKey]['rates'][$rate] ?? 0) + $incl;
                }
            }

            // Globale totalen afleiden uit de inclusieve bedragen per tarief.
            $btw = 0;
            $vatPercentages = [];
            foreach ($globalInclPerRate as $rate => $incl) {
                $rateVat = $vatFromIncl((float) $incl, (int) $rate);
                $vatPercentages[number_format($rate, 0)] = $rateVat;
                $btw += $rateVat;
            }
            $btw = round($btw, 2);
            $subTotal = round($total - $btw, 2);

            // Per zone: btw = som van de btw per tarief, ex = incl - btw. Zo klopt
            // ex * tarief == btw en blijft incl gelijk aan de ontvangen omzet.
            $finalizeZone = function (array $zone) use ($vatFromIncl): array {
                $zoneVat = 0.0;
                foreach ($zone['rates'] as $rate => $incl) {
                    $zoneVat += $vatFromIncl((float) $incl, (int) $rate);
                }
                $zone['vat'] = round($zoneVat, 2);
                $zone['ex_vat'] = round($zone['incl_vat'] - $zone['vat'], 2);
                unset($zone['rates']);

                return $zone;
            };

            $normalZoneTotals = collect($normalZoneTotals)
                ->map($finalizeZone)
                ->sortBy('zone')
                ->values()
                ->all();

            $ossTotals = collect($ossTotals)
                ->map($finalizeZone)
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
        $fileName = 'invoices-' . now()->format('Y-m-d-His') . '-' . Str::random(20) . '.pdf';
        $finalRelativePath = 'dashed/exports/' . now()->format('Y/m') . '/' . $this->exportId . '/' . $fileName;
        $sourceRelativePath = ltrim($invoicePath, '/');

        if (Storage::disk('public')->exists($sourceRelativePath)) {
            $contents = Storage::disk('public')->get($sourceRelativePath);
            Storage::disk('public')->put($finalRelativePath, $contents);
            Storage::disk('public')->delete($sourceRelativePath);
        }

        AdminNotifier::send(new FinanceExportMail(
            $this->hash,
            'Facturen van ' . ($startDate ? $startDate->format('d-m-Y') : 'het begin') . ' tot ' . ($endDate ? $endDate->format('d-m-Y') : 'nu'),
            $finalRelativePath,
        ), $this->email);

        $this->markExportAsCompleted($finalRelativePath, $fileName);
    }
}
