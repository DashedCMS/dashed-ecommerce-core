<?php

namespace Dashed\DashedEcommerceCore\Livewire\Orders\Infolists;

use Livewire\Component;
use Filament\Infolists\Infolist;
use Illuminate\Support\HtmlString;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\Storage;
use Filament\Infolists\Components\Fieldset;
use Dashed\DashedEcommerceCore\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Forms\Concerns\InteractsWithForms;
use Dashed\DashedEcommerceCore\Classes\CurrencyHelper;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class OrderProductsList extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Order $order;

    protected $listeners = [
        'refreshData' => '$refresh',
    ];

    public function mount($order)
    {
        $this->order = $order;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $orderProductsSchema = [];

        foreach ($this->order->orderProducts as $orderProduct) {
            $orderProductsSchema[] =
                Fieldset::make($orderProduct->name)
                    ->schema([
                        ImageEntry::make('image')
                            ->hiddenLabel()
                            ->visible($orderProduct->product && $orderProduct->product->firstImageUrl)
                            ->getStateUsing(fn () => app(\Dashed\Drift\UrlBuilder::class)->url('dashed', $orderProduct->product->firstImageUrl, [
                                'widen' => 600,
                            ]))
                            ->disk('dashed')
                            ->width('100%')
                            ->height('auto'),
                        TextEntry::make('productExtras')
                            ->label('Product extras')
                            ->visible(is_array($orderProduct->product_extras) ? count($orderProduct->product_extras ?: []) : false)
                            ->getStateUsing(function () use ($orderProduct) {
                                $productExtras = '';
                                if (is_array($orderProduct->product_extras ?: [])) {
                                    foreach ($orderProduct->product_extras ?: [] as $productExtra) {
                                        if ($productExtra['path'] ?? false) {
                                            $productExtras .= $productExtra['name'] . ': <a class="hover:text-primary-500" target="_blank" href="' . Storage::disk('dashed')->url($productExtra['path']) . '">' . $productExtra['value'] . '</a> <br/>';
                                        } else {
                                            $productExtras .= $productExtra['name'] . ': ' . $productExtra['value'] . ' <br/>';
                                        }
                                    }
                                }

                                return new HtmlString($productExtras);
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
                            ->getStateUsing(fn () => 'Is pre-order')
                            ->visible($orderProduct->is_pre_order),
                        TextEntry::make('price')
                            ->hiddenLabel()
                            ->getStateUsing(fn () => $orderProduct->price)
                            ->helperText(fn () => $orderProduct->discount > 0 ? 'Origineel ' . CurrencyHelper::formatPrice($orderProduct->price + $orderProduct->discount) : null)
                            ->money('EUR'),
                    ])
                    ->columns(5)
                    ->columnSpanFull();
        }

        return $infolist
            ->record($this->order)
            ->schema([
                Fieldset::make('Bestelde producten')
                    ->schema($orderProductsSchema),
            ]);
    }

    public function render()
    {
        return view('dashed-ecommerce-core::orders.components.infolists.plain-info-list');
    }
}
