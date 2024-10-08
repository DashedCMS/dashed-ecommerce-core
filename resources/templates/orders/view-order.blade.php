<x-container>
    <div class="grid grid-cols-6 gap-8 py-16 sm:py-24">
        <div class="col-span-6 lg:col-span-4">
            <h1 class="text-2xl font-bold">{{Translation::get('thanks-for-order-title', 'complete-order', 'Bedankt voor je bestelling!')}}</h1>
            <p>{{Translation::get('thanks-for-order-description', 'complete-order', 'We hebben je bestelling ontvangen en gaan hem verwerken!')}}</p>
            <div class="grid grid-cols-12 gap-4 mt-4">
                <div class="col-span-6">{{Translation::get('product', 'cart', 'Product')}}</div>
                <div
                    class="col-span-4 hidden lg:block">{{Translation::get('quantity', 'cart', 'Amount')}}</div>
                <div class="col-span-2 hidden lg:block">{{Translation::get('price', 'cart', 'Price')}}</div>
            </div>
            <hr class="mt-4 border-primary">
            @foreach($order->orderProducts as $orderProduct)
                <div class="grid grid-cols-12 gap-4 border-b border-primary py-4">
                    <div class="flex items-center space-x-4 col-span-12 lg:col-span-6">
                        @if($orderProduct->product && $orderProduct->product->firstImage)
                            <x-drift::image
                                class="mx-auto"
                                config="dashed"
                                :path="$orderProduct->product->firstImage"
                                :alt=" $orderProduct->product->name"
                                :manipulations="[
                                                    'widen' => 100,
                                                ]"
                            />
                        @endif
                        <div class="truncate">
                            {{$orderProduct->name}}
                            @if($orderProduct->product_extras)
                                @foreach($orderProduct->product_extras as $option)
                                    <br>
                                    <small>{{$option['name']}}: {{$option['value']}}</small>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="col-span-8 lg:col-span-4 flex items-center">
                        <div
                            class="rounded-full h-8 w-8 flex items-center justify-center text-white bg-primary-500">
                            {{$orderProduct->quantity}}x
                        </div>
                    </div>
                    <div class="col-span-4 lg:col-span-2 flex items-center">
                        {{CurrencyHelper::formatPrice($orderProduct->price)}}
                    </div>
                </div>
            @endforeach
            <div class="grid grid-cols-2 gap-4 mt-4">
                <div>
                    <h2 class="text-2xl font-bold">{{Translation::get('shipping-address', 'complete-order', 'Verzend adres')}}</h2>
                    <p>
                        @if($order->company_name)
                            {{$order->company_name}} <br>
                        @endif
                        {{ $order->first_name }} {{ $order->last_name }}<br>
                        @if($order->btw_id)
                            {{$order->btw_id}} <br>
                        @endif
                        {{ $order->street }} {{ $order->house_nr }}<br>
                        {{ $order->city }} {{ $order->zip_code }}<br>
                        {{ $order->country }}
                    </p>
                </div>
                @if($order->invoice_street)
                    <div>
                        <h2 class="text-2xl font-bold">{{Translation::get('invoice-address', 'complete-order', 'Factuur adres')}}</h2>
                        <p>
                            @if($order->company_name)
                                {{$order->company_name}} <br>
                            @endif
                            {{ $order->first_name }} {{ $order->last_name }}<br>
                            @if($order->btw_id)
                                {{$order->btw_id}} <br>
                            @endif
                            {{ $order->invoice_street }} {{ $order->invoice_house_nr }} <br>
                            {{ $order->invoice_city }} {{ $order->invoice_zip_code }} <br>
                            {{ $order->invoice_country }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-span-6 lg:col-span-2 p-4 bg-primary-800 rounded-md text-white">
            <h2 class="text-2xl">{{Translation::get('overview', 'cart', 'Overzicht')}}</h2>
            <hr class="my-4">
            <p>{{Translation::get('subtotal', 'cart', 'Subtotaal')}}: <span
                    class="float-right">{{CurrencyHelper::formatPrice($order->subtotal)}}</span></p>
            <hr class="my-2">
            @if($order->discount > 0)
                <p>
                    {{Translation::get('discount', 'cart', 'Korting')}}: <span
                        class="float-right">{{CurrencyHelper::formatPrice($order->discount)}}</span>
                </p>
                <hr class="my-2">
            @endif
            @if($order->shipping_costs > 0)
                <p>
                    {{Translation::get('shipping-costs', 'cart', 'Verzendkosten')}}: <span
                        class="float-right">{{CurrencyHelper::formatPrice($order->shipping_costs)}}</span>
                </p>
                <hr class="my-2">
            @endif
            <p>{{Translation::get('btw', 'cart', 'BTW')}}: <span
                    class="float-right">{{CurrencyHelper::formatPrice($order->btw)}}</span></p>
            <hr class="my-2">
            <p>{{Translation::get('total', 'cart', 'Totaal')}}: <span
                    class="float-right">{{CurrencyHelper::formatPrice($order->total)}}</span></p>
            <hr class="my-2">
            <p>{{Translation::get('payment-method', 'cart', 'Betaalmethode')}}:
                <span class="float-right">
                        {{$order->payment_method ?: $order->paymentMethod->name}}
                    </span>
            </p>
            <hr class="my-2">
            <p>{{Translation::get('shipping-method', 'cart', 'Verzendmethode')}}:
                <span class="float-right">
                        {{$order->shippingMethod->name}}
                    </span>
            </p>
            @if($order->note)
                <hr class="my-2">
                <p>{{Translation::get('note', 'cart', 'Notitie')}}:
                </p>
                <p>
                    {{$order->note}}
                </p>
            @endif
            <div class="flex mt-6">
                <a href="{{$order->downloadInvoiceUrl()}}"
                   class="button button--primary-light w-full uppercase text-center">
                    {{Translation::get('download-invoice', 'cart', 'Download factuur')}}
                </a>
            </div>
        </div>
    </div>
</x-container>
