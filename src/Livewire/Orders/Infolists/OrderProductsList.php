<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Storage;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;

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

    public function infolist(Schema $schema): Schema
    {
        $productComponents = [];

        foreach ($this->order->orderProducts as $orderProduct) {
            $pid = $orderProduct->id ?? spl_object_id($orderProduct);

            $productComponents[] = Fieldset::make('product_' . $pid)
                ->label($orderProduct->name)
                ->schema([
                    ImageEntry::make('image_' . $pid)
                        ->hiddenLabel()
                        ->visible((bool)($orderProduct->product && $orderProduct->product->firstImage))
                        ->state(fn () => $orderProduct->custom_image ?: (mediaHelper()->getSingleMedia($orderProduct->product->firstImage)->url ?? ''))
                        ->disk('dashed')
                        ->width('100%')
                        ->height('auto'),

                    TextEntry::make('product_extras_' . $pid)
                        ->hiddenLabel()
                        ->visible(
                            (is_array($orderProduct->product_extras) ? count($orderProduct->product_extras ?: []) > 0 : false)
                            || count($orderProduct->hidden_options ?: []) > 0
                        )
                        ->state(function () use ($orderProduct) {
                            $html = '';

                            if (is_array($orderProduct->product_extras ?: [])) {
                                foreach ($orderProduct->product_extras as $productExtra) {
                                    if ($productExtra['path'] ?? false) {
                                        $html .= e($productExtra['name']) . ': <a class="hover:text-primary-500" target="_blank" href="' .
                                            e(Storage::disk('dashed')->url($productExtra['path'])) . '">' . e($productExtra['value']) . '</a> <br/>';
                                    } else {
                                        $html .= e($productExtra['name']) . ': ' . e($productExtra['value']) . ' <br/>';
                                    }
                                }
                            }

                            if (is_array($orderProduct->hidden_options ?: [])) {
                                foreach ($orderProduct->hidden_options ?: [] as $key => $value) {
                                    if (! str($value)->contains('base64')) {
                                        $html .= e($key) . ': ' . e($value) . ' <br/>';
                                    }
                                }
                            }

                            return new HtmlString($html);
                        })
                        ->size('xs'),
                    TextEntry::make('quantity')
                        ->hiddenLabel()
                        ->badge()
                        ->color('primary')
                        ->weight('bold')
                        ->getStateUsing(fn () => $orderProduct->quantity)
                        ->suffix('x'),
                    TextEntry::make('preOrder')
                        ->hiddenLabel()
                        ->badge()
                        ->color('warning')
                        ->weight('bold')
                        ->getStateUsing(function () use ($orderProduct) {
                            // Voorkeur: snapshot op de order_product zelf. Voor
                            // orders van vóór de resolver, of als de waarde
                            // toch leeg blijkt, fallback op het live product
                            // (dat zowel expected_in_stock_date als
                            // expected_delivery_in_days afhandelt).
                            $raw = $orderProduct->pre_order_restocked_date
                                ?: $orderProduct->product?->resolvePreOrderRestockedDate();

                            $date = $raw
                                ? \Illuminate\Support\Carbon::parse($raw)->format('d-m-Y')
                                : null;

                            return $date
                                ? 'Pre-order ' . $date
                                : 'Pre-order';
                        })
                        ->visible($orderProduct->is_pre_order),
                    TextEntry::make('price')
                        ->hiddenLabel()
                        ->getStateUsing(fn () => $orderProduct->price)
                        ->helperText(fn () => $orderProduct->discount > 0 ? 'Origineel ' . CurrencyHelper::formatPrice($orderProduct->price + $orderProduct->discount) : null)
                        ->money('EUR'),
                    TextEntry::make('fulfiller')
                        ->hiddenLabel()
                        ->visible((bool)$orderProduct->fulfillment_provider)
                        ->getStateUsing(fn () => ($orderProduct->send_to_fulfiller ? 'Doorgestuurd naar ' : 'Moet nog doorgestuurd worden naar ') . ($orderProduct->fulfillmentCompany->name ?? $orderProduct->fulfillment_provider))
                        ->badge()
                        ->columnSpanFull()
                        ->color(fn () => $orderProduct->send_to_fulfiller ? 'success' : 'warning'),
                    TextEntry::make('name')
                        ->hiddenLabel()
                        ->state(fn () => $orderProduct->name)
                        ->visible(fn () => $orderProduct->product)
                        ->getStateUsing(fn () => '<a class="hover:text-primary-500" target="_blank" href="' . ($orderProduct->product ? route('filament.dashed.resources.products.edit', $orderProduct->product) : '#') . '">' . 'Bekijk product' . '</a>')
                        ->size('sm')
                        ->columnSpanFull()
                        ->html(),
                ])
                ->columns(5)
                ->columnSpanFull();
        }

        return $schema
            ->record($this->order)
            ->components([
                Fieldset::make('ordered_products')
                    ->label('Bestelde producten')
                    ->schema($productComponents)
                    ->columnSpanFull(),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
