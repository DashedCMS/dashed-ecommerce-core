<main class="relative lg:min-h-full">
    <div class="h-80 overflow-hidden lg:absolute lg:h-full lg:w-1/2 lg:pr-4 xl:pr-12">
        <x-dashed-files::image
            class="h-full w-full object-cover object-center"
            :mediaId="Translation::get('cover-image', 'view-order', '', 'image')"
            :manipulations="[
                        'widen' => 1000,
                    ]"
        />
    </div>

    <div>
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6 sm:py-24 lg:grid lg:max-w-7xl lg:grid-cols-2 lg:gap-x-8 lg:px-8 lg:py-32 xl:gap-x-24">
            <div class="lg:col-start-2">
                <h1 class="text-sm font-medium text-primary-600">{{Translation::get('payment-successful', 'complete-order', 'Betaling gelukt')}}</h1>
                <p class="mt-2 text-4xl font-bold tracking-tight text-gray-900">{{Translation::get('thanks-for-order-title', 'complete-order', 'Bedankt voor je bestelling!')}}</p>
                <p class="mt-2 text-base text-gray-500">{{Translation::get('thanks-for-order-description', 'complete-order', 'We hebben je bestelling ontvangen en gaan hem verwerken!')}}</p>

                @if($order->mainPaymentMethod && $order->mainPaymentMethod->payment_instructions)
                    <div class="mt-6 text-sm text-orange-500 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                            <path fill-rule="evenodd"
                                  d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
                                  clip-rule="evenodd"/>
                        </svg>

                        <span>{!! $order->mainPaymentMethod->payment_instructions !!}</span>
                    </div>
                @endif

                @if($order->trackAndTraces()->count())
                    @foreach($order->trackAndTraces as $trackAndTrace)
                        <a href="{{ $trackAndTrace->url ?: '#' }}" target="_blank" class="mt-6 text-sm font-medium">
                            <dt class="text-gray-900">{{ Translation::get('tracking-number', 'complete-order', 'Track en trace van :deliveryCompany:', 'text', [
                                'deliveryCompany' => $trackAndTrace->delivery_company
                            ]) }}</dt>
                            <dd class="mt-2 text-primary-600">{{ $trackAndTrace->code }}</dd>
                        </a>
                    @endforeach
                @else
                    <div class="mt-6 text-sm text-orange-500 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                            <path fill-rule="evenodd"
                                  d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
                                  clip-rule="evenodd"/>
                        </svg>

                        <span>{{ Translation::get('no-tracking-numbers-available', 'complete-order', 'Er zijn (nog) geen track & trace codes beschikbaar.') }}</span>
                    </div>
                @endif

                <ul role="list"
                    class="mt-6 divide-y divide-gray-200 border-t border-gray-200 text-sm font-medium text-gray-500">
                    @foreach($order->orderProducts as $orderProduct)
                        <li class="flex space-x-6 py-6">
                            @if($orderProduct->product && $orderProduct->product->firstImage)
                                <x-drift::image
                                    class="h-24 w-24 flex-none rounded-md bg-gray-100 object-cover object-center"
                                    config="dashed"
                                    :path="$orderProduct->product->firstImage"
                                    :alt=" $orderProduct->product->name"
                                    :manipulations="[
                                                    'widen' => 100,
                                                ]"
                                />
                            @endif
                            <div class="flex-auto space-y-1">
                                <h3 class="text-gray-900">
                                    <a href="{{ $orderProduct->product ? $orderProduct->product->getUrl() : '#' }}">
                                        {{$orderProduct->name}}
                                    </a>
                                </h3>
                                @if($orderProduct->product_extras)
                                    @foreach($orderProduct->product_extras as $option)
                                        <p>{{$option['name']}}: {{$option['value']}}</p>
                                    @endforeach
                                @endif
                            </div>
                            <p class="flex-none font-medium text-gray-900">{{CurrencyHelper::formatPrice($orderProduct->price)}}</p>
                        </li>
                    @endforeach
                </ul>

                <dl class="space-y-6 border-t border-gray-200 pt-6 text-sm font-medium text-gray-500">
                    <div class="flex justify-between">
                        <dt>{{ Translation::get('subtotal', 'cart', 'Subtotaal') }}</dt>
                        <dd class="text-gray-900">{{CurrencyHelper::formatPrice($order->subtotal)}}</dd>
                    </div>

                    @if($order->discount > 0)
                        <div class="flex justify-between">
                            <dt>{{ Translation::get('discount', 'cart', 'Korting') }}</dt>
                            <dd class="text-gray-900">{{CurrencyHelper::formatPrice($order->discount)}}</dd>
                        </div>
                    @endif
                    @if($order->shipping_costs > 0)
                        <div class="flex justify-between">
                            <dt>{{ Translation::get('shipping-costs', 'cart', 'Verzendkosten') }}</dt>
                            <dd class="text-gray-900">{{CurrencyHelper::formatPrice($order->shipping_costs)}}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <dt>{{ Translation::get('shipping', 'cart', 'Verzendkosten') }}</dt>
                        <dd class="text-gray-900">$8.00</dd>
                    </div>

                    <div class="flex justify-between">
                        <dt>{{ Translation::get('btw', 'cart', 'BTW') }}</dt>
                        <dd class="text-gray-900">{{CurrencyHelper::formatPrice($order->btw)}}</dd>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-200 pt-6 text-gray-900">
                        <dt class="text-base">{{ Translation::get('total', 'cart', 'Totaal') }}</dt>
                        <dd class="text-base">{{CurrencyHelper::formatPrice($order->total)}}</dd>
                    </div>
                </dl>


                <dl class="mt-16 grid grid-cols-2 gap-4 text-sm text-gray-600">
                    <div>
                        <dt class="font-medium text-gray-900">{{ Translation::get('payment-method', 'cart', 'Betaalmethode') }}</dt>
                        <dd class="mt-2">
                            {{$order->payment_method ?: $order->paymentMethod->name}}
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-900">{{Translation::get('shipping-method', 'cart', 'Verzendmethode')}}</dt>
                        <dd class="mt-2">
                            {{$order->shippingMethod->name}}
                        </dd>
                    </div>
                    @if($order->note)
                        <div>
                            <dt class="font-medium text-gray-900">{{Translation::get('note', 'cart', 'Notitie')}}</dt>
                            <dd class="mt-2">
                                {{$order->note}}
                            </dd>
                        </div>
                    @endif

                    <div>
                        <dt class="font-medium text-gray-900">{{Translation::get('shipping-address', 'complete-order', 'Verzend adres')}}</dt>
                        <dd class="mt-2">
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
                        </dd>
                    </div>
                    @if($order->invoice_street)
                        <div>
                            <dt class="font-medium text-gray-900">{{Translation::get('invoice-address', 'complete-order', 'Factuur adres')}}</dt>
                            <dd class="mt-2">
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
                            </dd>
                        </div>
                    @endif
                </dl>

                <div class="flex mt-6">
                    <a href="{{$order->downloadInvoiceUrl()}}"
                       class="button button--primary w-full uppercase text-center">
                        {{Translation::get('download-invoice', 'cart', 'Download factuur')}}
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>
