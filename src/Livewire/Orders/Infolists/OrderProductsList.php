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
                                        $html .= $productExtra['name'] . ': <a class="hover:text-primary-500" target="_blank" href="' .
                                            Storage::disk('dashed')->url($productExtra['path']) . '">' . $productExtra['value'] . '</a> <br/>';
                                    } else {
                                        $html .= $productExtra['name'] . ': ' . $productExtra['value'] . ' <br/>';
                                    }
                                }
                            }

                            if (is_array($orderProduct->hidden_options ?: [])) {
                                foreach ($orderProduct->hidden_options ?: [] as $key => $value) {
                                    if (! str($value)->contains('base64')) {
                                        $html .= $key . ': ' . $value . ' <br/>';
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
                        ->getStateUsing(fn () => 'Is pre-order')
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
