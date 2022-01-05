<x-filament::page>

    <form wire:submit.prevent="submit" method="POST">
        {{ $this->form }}
    </form>

    <div>
        <div class="mt-4">
            <div class="grid grid-cols-6 gap-8">
                <div class="col-span-4 space-y-4">
                    <div class="text-sm text-gray-500 space-x-2 space-y-2 bg-white rounded-md p-4">
                        <span class="font-bold">Status:</span>
                        @php
                            $labels = $record->statusLabels;
                            $labels = array_merge($labels, [$record->orderStatus()]);
                            $labels = array_merge($labels, [$record->fulfillmentStatus()]);
                        @endphp

                        @foreach($labels as $label)
                            <span
                                class="bg-{{ $label['color'] }}-100 text-{{ $label['color'] }}-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                {{ $label['status'] }}
                                </span>
                        @endforeach
                        @foreach($record->creditOrders as $creditOrder)
                            <a href="{{ route('filament.resources.orders.view', [$creditOrder]) }}"
                               class="bg-red-100 text-red-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                Credit {{ $creditOrder->invoice_id }}
                            </a>
                        @endforeach
                        @if($record->credit_for_order_id)
                            <a href="{{ route('filament.resources.orders.view', [$record->parentCreditOrder]) }}"
                               class="bg-green-100 text-green-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                Credit voor {{ $record->parentCreditOrder->invoice_id }}
                            </a>
                        @endif
                    </div>
                    <div class="bg-white rounded-md p-4">
                        <h2 class="text-2xl font-bold mb-4">Bestelde producten</h2>
                        @foreach($record->orderProducts as $orderProduct)
                            <div class="grid grid-cols-12 gap-4 border-t border-primary py-4">
                                <div class="flex items-center space-x-4 col-span-12 lg:col-span-6">
                                    @if($orderProduct->product && $orderProduct->product->firstImageUrl)
                                        <x-glide::image
                                            :src="$orderProduct->product->firstImageUrl"
                                            w="100"
                                            h="auto"
                                            fit="contain"
                                        />
                                    @endif
                                    <div class="">
                                        {{ $orderProduct->name }}
                                        @if($orderProduct->product_extras)
                                            @foreach($orderProduct->product_extras as $extra)
                                                <div class="text-xs">
                                                    {{ $extra['name'] }}: {{ $extra['value'] }}
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                                <div class="col-span-8 lg:col-span-4 flex items-center space-x-4">
                                    <div
                                        class="rounded-full h-8 w-8 flex items-center justify-center text-white bg-primary-500">
                                        {{ $orderProduct->quantity }}x
                                    </div>
                                    @if($orderProduct->is_pre_order)
                                        <span
                                            class="bg-yellow-100 text-yellow-800 inline-flex items-center px-3 py-0.5 rounded-full text-sm font-medium">
                                            Is pre-order
                                        </span>
                                    @endif
                                </div>
                                <div class="col-span-4 lg:col-span-2 flex items-center">
                                    {{ CurrencyHelper::formatPrice($orderProduct->price) }}
                                    @if($orderProduct->discount > 0)
                                        <span
                                            class="text-sm line-through ml-2">{{ CurrencyHelper::formatPrice($orderProduct->price + $orderProduct->discount) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="bg-white rounded-md p-4">
                        <div class="grid grid-cols-3 gap-4 mt-4">
                            <div>
                                <h2 class="text-2xl font-bold">
                                    Overige informatie
                                </h2>
                                <p>
                                    Bestelling herkomst: {{ $record->order_origin }}
                                    <br>
                                    IP: {{ $record->ip }}
                                    <br>
                                    Notitie: {{ $record->note ?: 'Geen notitie' }}
                                    <br>
                                    Marketing geaccepteerd: {{ $record->marketing ? 'Ja' : 'Nee' }}
                                    <br>
                                    Factuur ID: {{ $record->invoice_id }}
                                    <br>
                                    Betalingsmethode: {{ $record->payment_method_name }}
                                    @if($record->psp)
                                        (PSP: {{ $record->psp }})
                                    @endif
                                    <br>
                                    @if($record->psp_id)
                                        PSP ID: {{ $record->psp_id }}
                                        <br>
                                    @endif
                                    Verzendmethode: {{ $record->shipping_method_name }}
                                    <br>
                                    Subtotaal: {{ CurrencyHelper::formatPrice($record->subtotal) }}
                                    <br>
                                    Korting: {{ CurrencyHelper::formatPrice($record->discount) }} @if($record->discount_code)
                                        (Code: {{ $record->discount_code->code }}) @endif
                                    <br>
                                    BTW: {{ CurrencyHelper::formatPrice($record->btw) }}
                                    <br>
                                    Totaal: {{ CurrencyHelper::formatPrice($record->total) }}
                                </p>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold">
                                    Verzendadres</h2>
                                <p>
                                    @if($record->company_name)
                                        {{ $record->company_name }} <br>
                                    @endif
                                    {{ $record->name }}<br>
                                    @if($record->btw_id)
                                        {{ $record->btw_id }} <br>
                                    @endif
                                    {{ $record->street }} {{ $record->house_nr }}<br>
                                    {{ $record->city }} {{ $record->zip_code }}<br>
                                    {{ $record->country }}
                                </p>
                            </div>
                            @if($record->invoice_street)
                                <div>
                                    <h2 class="text-2xl font-bold">
                                        Factuuradres</h2>
                                    <p>
                                        @if($record->company_name)
                                            {{ $record->company_name }} <br>
                                        @endif
                                        {{ $record->invoice_name }}<br>
                                        @if($record->btw_id)
                                            {{ $record->btw_id }} <br>
                                        @endif
                                        {{ $record->invoice_street }} {{ $record->invoice_house_nr }} <br>
                                        {{ $record->invoice_city }} {{ $record->invoice_zip_code }} <br>
                                        {{ $record->invoice_country }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="bg-white rounded-md p-4">
                        <h2 class="text-2xl font-bold">
                            Betalingen
                        </h2>
                        <div class="grid grid-cols-3 gap-4 mt-4">
                            @foreach($record->orderPayments as $orderPayment)
                                <div>
                                    <p>
                                        PSP: {{ $orderPayment->psp }}
                                    </p>
                                    <p>
                                        PSP ID: {{ $orderPayment->psp_id }}
                                    </p>
                                    <p>
                                        Betaalmethode: {{ $orderPayment->payment_method_name }}
                                    </p>
                                    <p>
                                        Bedrag: {{ CurrencyHelper::formatPrice($orderPayment->amount) }}
                                    </p>
                                    <p>
                                        Status: {{ $orderPayment->status }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @foreach(ecommerce()->widgets('orders') as $widget)
                        @if($widget['width'] == 'full')
                            <hr>
                            <livewire:is :component="$widget['name']" :order="$record"></livewire:is>
                        @endif
                    @endforeach
                </div>
                <div class="col-span-2 space-y-4">
                    <div class="bg-white rounded-md p-4 space-y-2">
                        @if(!$record->credit_for_order_id)
                            <livewire:change-order-fulfillment-status
                                :order="$record"></livewire:change-order-fulfillment-status>
                            <hr>
                            <livewire:add-payment-to-order
                                :order="$record"></livewire:add-payment-to-order>
                            <hr>
                            @if(($record->status == 'paid' || $record->status == 'waiting_for_confirmation' || $record->status == 'partially_paid') && $record->order_origin == 'own')
                                <a href="{{ route('filament.resources.orders.cancel', [$record]) }}"
                                   class="inline-flex items-center justify-center font-medium tracking-tight rounded-lg focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 h-9 px-4 text-white shadow focus:ring-white w-full mt-2 w-full">
                                    Annuleer bestelling
                                </a>
                                <hr>
                            @elseif(($record->status == 'paid' || $record->status == 'waiting_for_confirmation' || $record->status == 'partially_paid') && $record->order_origin != 'own')
                                <a href="#"
                                   class="inline-flex items-center justify-center font-medium tracking-tight rounded-lg focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 h-9 px-4 text-white shadow focus:ring-white w-full mt-2 w-full">
                                    Annuleer bestelling
                                </a>
                                <hr>
                            @endif
                            <livewire:send-order-confirmation-to-email
                                :order="$record"></livewire:send-order-confirmation-to-email>
                        @else
                            <livewire:change-order-retour-status
                                :order="$record"></livewire:change-order-retour-status>
                        @endif

                        @foreach(ecommerce()->widgets('orders') as $widget)
                            @if($widget['width'] == 'sidebar')
                                <hr>
                                <livewire:is :component="$widget['name']" :order="$record"></livewire:is>
                            @endif
                        @endforeach
                    </div>
                    <div class="bg-white rounded-md p-4">
                        <div class="space-y-4">
                            <img class="mx-auto h-20 rounded-full lg:h-24"
                                 src="{{ Helper::getProfilePicture($record->email) }}"
                                 alt="">
                            <div class="space-y-2 text-center">
                                <div class="text-xs font-medium lg:text-sm grid">
                                    <h3>{{ $record->name }}</h3>
                                    @if($record->phone_number)
                                        <a href="tel:{{$record->phone_number}}" class="text-indigo-600">
                                            {{ $record->phone_number }}
                                        </a>
                                    @endif
                                    <a href="mailto:{{$record->email}}" class="text-indigo-600">
                                        {{ $record->email }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-md p-4">
                        <ul class="divide-y divide-gray-200">
                            @foreach($record->logs as $log)
                                <li class="py-4">
                                    <div class="flex space-x-3">
                                        <img class="h-6 w-6 rounded-full"
                                             src="{{ Helper::getProfilePicture($record->user ? $record->user->email : $record->email) }}"
                                             alt="">
                                        <div class="flex-1 space-y-1">
                                            <div class="flex items-center justify-between">
                                                <h3 class="text-sm font-medium">{{ $log->user_id ? $log->user->name : ($record->user ? $record->user->name : (\Illuminate\Support\Str::contains('system', $record->tag) ? 'System' : $record->name)) }}</h3>
                                                <p class="text-sm text-gray-500">
                                                    {{ $log->created_at > now()->subHour() ? $log->created_at->diffForHumans() : $log->created_at->format('d-m-Y H:i') }}
                                                </p>
                                            </div>
                                            <p class="text-sm text-gray-500">{{ $log->tag() }}</p>
                                            @if($log->public_for_customer)
                                                <p class="text-sm text-gray-500">
                                                    Klant heeft een email gehad</p>
                                            @endif
                                            @if($log->note)
                                                <p class="text-sm text-gray-900">{!! nl2br($log->note) !!}</p>
                                            @endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                            <li class="py-4">
                                <livewire:create-order-log :order="$record"></livewire:create-order-log>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-filament::page>
