<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Exports;

use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Qubiqx\QcommerceCore\Classes\Sites;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Qubiqx\QcommerceCore\Models\Customsetting;
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

    protected function getFormSchema(): array
    {
        return [
                Radio::make("checkout_account_")
                    ->label('Klantaccounts')
                    ->options([
                        'disabled' => 'Accounts zijn uitgeschakeld',
                        'optional' => 'Accounts zijn optioneel',
                        'required' => 'Account vereist',
                    ])
                    ->required(),
            ];
    }

    public function submit()
    {
        $orders = Order::with(['orderProducts', 'orderProducts.product'])->calculatableForStats();
        if (request()->get('beginDate') != null) {
            $orders->where('created_at', '>=', Carbon::parse(request()->get('beginDate'))->startOfDay());
        }

        if (request()->get('endDate') != null) {
            $orders->where('created_at', '<=', Carbon::parse(request()->get('endDate'))->endOfDay());
        }
        $orders = $orders->get();

        if ($request->sort == 'merged') {
            $pdfMerger = PDFMerger::init();

            foreach ($orders as $order) {
                $invoicePath = storage_path('app/invoices/invoice-' . $order->invoice_id . '-' . $order->hash . '.pdf');
                $pdfMerger->addPdf($invoicePath, 'all');
            }

            $pdfMerger->merge();
            $pdfMerger->save(storage_path('app/invoices/exported-invoice.pdf'));
        } elseif ($request->sort == 'combined') {
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
//                $paymentCosts += $order->payment_costs;
//                $shippingCosts += $order->shipping_costs;
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

            $view = View::make('qcommerce::frontend.combined-invoices.pdf', compact('subTotal', 'btw', 'paymentCosts', 'shippingCosts', 'discount', 'total', 'productSales'));
            $contents = $view->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($contents);
            $output = $pdf->output();

            $invoicePath = '/invoices/exported-invoice.pdf';
            Storage::put($invoicePath, $output);
        } else {
            $this->notify('error', 'De export kon niet worden gemaakt');
        }

        $this->notify('success', 'De export is gedownload');
    }
}
