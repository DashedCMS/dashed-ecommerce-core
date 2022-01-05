<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\PieChartWidget;
use Illuminate\Support\Facades\Cache;
use Qubiqx\QcommerceEcommerceCore\Models\OrderPayment;
use Qubiqx\QcommerceEcommerceCore\Models\PaymentMethod;

class PaymentMethodPieChartWidget extends PieChartWidget
{
    protected function getData(): array
    {
        $data = Cache::remember('payment-pie-chart-data', 60 * 60, function () {
            $paymentMethods = OrderPayment::whereNotNull('payment_method')->distinct('payment_method')->pluck('payment_method');
            $orderPayments = OrderPayment::get();
            foreach ($orderPayments as $orderPayment) {
                if (! $orderPayment->payment_method_id) {
                    $correctPaymentMethod = PaymentMethod::where('psp', $orderPayment->psp)->where('name', 'LIKE', '%' . $orderPayment->payment_method . '%')->first();
                    if ($correctPaymentMethod) {
                        $orderPayment->payment_method_id = $correctPaymentMethod->id;
                        $orderPayment->save();
                    }
                }
            }

            $pieData = [];
            $pieColors = [];
            $pieLabels = [];

            foreach ($paymentMethods as $paymentMethod) {
                $pieData[] = OrderPayment::paid()->where('payment_method', $paymentMethod)->count();
                $pieLabels[] = $paymentMethod;
                $pieColors[] = '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
            }

            return [
                'pieData' => $pieData,
                'pieColors' => $pieColors,
                'pieLabels' => $pieLabels,
            ];
        });

        return [
            'datasets' => [
                [
                    'data' => $data['pieData'],
                    'backgroundColor' => $data['pieColors'],
                ],
            ],
            'labels' => $data['pieLabels'],
        ];
    }

    protected function getHeading(): ?string
    {
        return 'Gebruikte betaalmethodes';
    }
}
