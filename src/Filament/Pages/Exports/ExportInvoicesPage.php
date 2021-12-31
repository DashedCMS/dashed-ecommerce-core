<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports;

use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Qubiqx\QcommerceEcommerceCore\Models\Order;
use Qubiqx\QcommerceEcommerceCore\Models\Product;

class ExportInvoicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-download';
    protected static ?string $navigationLabel = 'Exporteer facturen';
    protected static ?string $navigationGroup = 'Export';
    protected static ?string $title = 'Exporteer facturen';
    protected static ?int $navigationSort = 100000;

    protected static string $view = 'qcommerce-ecommerce-core::exports.pages.export-invoices';

    public function mount(): void
    {
        $this->form->fill([
            'sort' => 'merged',
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('start_date')
                ->label('Start datum')
                ->rules([
                    'nullable',
                ]),
            DatePicker::make('end_date')
                ->label('Eind datum')
                ->rules([
                    'nullable',
                    'after:start_date',
                ]),
            Select::make('sort')
                ->label('Soort export')
                ->options([
                    'merged' => 'Alle facturen in 1 PDF',
                    'combined' => 'Alle orders in 1 factuur',
                ])
                ->rules([
                    'required',
                ])
                ->required(),
        ];
    }

    public function submit()
    {
        $orders = Order::with(['orderProducts', 'orderProducts.product'])->where('order_origin', 'own')->calculatableForStats();
        if ($this->form->getState()['start_date'] != null) {
            $orders->where('created_at', '>=', Carbon::parse($this->form->getState()['start_date'])->startOfDay());
        }

        if ($this->form->getState()['end_date'] != null) {
            $orders->where('created_at', '<=', Carbon::parse($this->form->getState()['end_date'])->endOfDay());
        }
        $orders = $orders->get();

        if ($this->form->getState()['sort'] == 'merged') {
            $pdfMerger = \PDFMerger::init();

            foreach ($orders as $order) {
                $url = $order->downloadInvoiceUrl();
                if ($url) {
                    $invoicePath = storage_path('app/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                    $pdfMerger->addPathToPDF($invoicePath, 'all');
                }
            }

            $pdfMerger->merge();
            $pdfMerger->download();
        } elseif ($this->form->getState()['sort'] == 'combined') {
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

            $view = View::make('qcommerce-ecommerce-core::frontend.combined-invoices.pdf', compact('subTotal', 'btw', 'paymentCosts', 'shippingCosts', 'discount', 'total', 'productSales'));
            $contents = $view->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($contents);
            $output = $pdf->output();

            $invoicePath = '/exports/invoices/exported-invoice.pdf';
            Storage::disk('public')->put($invoicePath, $output);
        } else {
            $this->notify('error', 'De export kon niet worden gemaakt');
        }

        $this->notify('success', 'De export is gedownload');

        return Storage::disk('public')->download('/exports/invoices/exported-invoice.pdf');
    }
}
