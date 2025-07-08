<?php

namespace Dashed\DashedEcommerceCore\Filament\Widgets\Revenue;

use Dashed\DashedEcommerceCore\Models\EcommerceActionLog;
use Dashed\DashedEcommerceCore\Models\Product;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\PieChartWidget;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedEcommerceCore\Models\OrderPayment;
use Dashed\DashedEcommerceCore\Models\PaymentMethod;

class CartActionsPieChartWidget extends ChartWidget
{
    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $data = Cache::remember('add-to-cart-pie-chart-data', 60 * 60, function () {
            $pieData = [];
            $pieColors = [];
            $pieLabels = [];

            $addToCartActions = EcommerceActionLog::where('action_type', 'add_to_cart')
                ->where('created_at', '>=', now()->subDays(30))
                ->get();
            $products = Product::whereIn('id', $addToCartActions->pluck('product_id')->unique())->get();
            foreach ($products as $product) {
                $pieLabels[] = $product->name;
                $pieData[] = $addToCartActions->where('product_id', $product->id)->sum('quantity');
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
        return 'Toegevoegde producten in winkelwagentje (30 dagen)';
    }
}
