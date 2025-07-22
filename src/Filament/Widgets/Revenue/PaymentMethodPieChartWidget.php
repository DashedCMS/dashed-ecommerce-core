<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

class PaymentMethodPieChartWidget extends ChartWidget
{
    protected function getType(): string
    {
        return 'pie';
    }

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

    public function getHeading(): ?string
    {
        return 'Gebruikte betaalmethodes';
    }
}
