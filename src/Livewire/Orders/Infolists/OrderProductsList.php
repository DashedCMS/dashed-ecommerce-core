<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Dashed\DashedEcommerceCore\Classes\ProductExtraFile;

class OrderProductsList extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ($this->order->orderProducts as $orderProduct) {
            $image = null;
            if ($orderProduct->custom_image) {
                $image = $orderProduct->custom_image;
            } elseif ($orderProduct->product && $orderProduct->product->firstImage) {
                $image = mediaHelper()->getSingleMedia($orderProduct->product->firstImage)->url ?? null;
            }
            if ($image && ! str($image)->startsWith(['http://', 'https://', '/'])) {
                $image = Storage::disk('dashed')->url($image);
            }

            $badges = [];
            if ($orderProduct->is_pre_order) {
                // Voorkeur: snapshot op de order_product zelf. Voor orders van
                // vóór de resolver, of als de waarde toch leeg blijkt,
                // fallback op het live product (dat zowel
                // expected_in_stock_date als expected_delivery_in_days
                // afhandelt).
                $raw = $orderProduct->pre_order_restocked_date
                    ?: $orderProduct->product?->resolvePreOrderRestockedDate();
                $date = $raw ? Carbon::parse($raw)->format('d-m-Y') : null;
                $badges[] = ['label' => $date ? 'Pre-order ' . $date : 'Pre-order', 'color' => 'warning'];
            }
            if ($orderProduct->added_via) {
                $badges[] = ['label' => 'Cross/up-sell', 'color' => 'info'];
            }
            if ($orderProduct->fulfillment_provider) {
                $badges[] = [
                    'label' => ($orderProduct->send_to_fulfiller ? 'Doorgestuurd naar ' : 'Moet nog doorgestuurd worden naar ') . ($orderProduct->fulfillmentCompany->name ?? $orderProduct->fulfillment_provider),
                    'color' => $orderProduct->send_to_fulfiller ? 'success' : 'warning',
                ];
            }

            $extras = '';
            if (is_array($orderProduct->product_extras)) {
                foreach ($orderProduct->product_extras as $productExtra) {
                    if ($productExtra['path'] ?? false) {
                        $extras .= e($productExtra['name']) . ': <a target="_blank" href="' .
                            e(ProductExtraFile::adminUrl($productExtra['path'])) . '">' . e($productExtra['value']) . '</a><br/>';
                    } else {
                        $extras .= e($productExtra['name']) . ': ' . e($productExtra['value']) . '<br/>';
                    }
                }
            }
            if (is_array($orderProduct->hidden_options)) {
                foreach ($orderProduct->hidden_options as $key => $value) {
                    if (! str($value)->contains('base64')) {
                        $extras .= e($key) . ': ' . e($value) . '<br/>';
                    }
                }
            }

            $lines[] = [
                'name' => $orderProduct->name,
                'url' => $orderProduct->product ? route('filament.dashed.resources.products.edit', $orderProduct->product) : null,
                'image' => $image,
                'badges' => $badges,
                'extras' => $extras ? new HtmlString($extras) : null,
                'quantity' => $orderProduct->quantity,
                'price' => CurrencyHelper::formatPrice($orderProduct->price),
                'original' => $orderProduct->discount > 0 ? CurrencyHelper::formatPrice($orderProduct->price + $orderProduct->discount) : null,
            ];
        }

        return $lines;
    }

    /**
     * @return array<int, array{label: string, value: string, hint: ?string, total: bool}>
     */
    public function totals(): array
    {
        $order = $this->order;
        $rows = [
            ['label' => __('Subtotaal'), 'value' => CurrencyHelper::formatPrice($order->subtotal), 'hint' => null, 'total' => false],
        ];

        if ((float) $order->discount != 0.0) {
            $rows[] = ['label' => __('Korting'), 'value' => '- ' . CurrencyHelper::formatPrice($order->discount), 'hint' => $order->discountCode?->code, 'total' => false];
        }

        $vatPercentages = array_filter((array) ($order->vat_percentages ?: []));
        if ($vatPercentages) {
            foreach ($vatPercentages as $percentage => $amount) {
                $rows[] = ['label' => __('Waarvan btw (:percentage%)', ['percentage' => number_format((float) $percentage, 0)]), 'value' => CurrencyHelper::formatPrice((float) $amount), 'hint' => null, 'total' => false];
            }
        } else {
            $rows[] = ['label' => __('BTW'), 'value' => CurrencyHelper::formatPrice($order->btw), 'hint' => null, 'total' => false];
        }

        $rows[] = ['label' => __('Totaal'), 'value' => CurrencyHelper::formatPrice($order->total), 'hint' => $order->vat_reverse_charge ? __('BTW verlegd') : null, 'total' => true];

        return $rows;
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.order-products');
    }
}
