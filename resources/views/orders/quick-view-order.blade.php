<div class="grid gap-4">
    <div class="col-span-full">
        <div id="mountedTableActionData.bestelde-producten"
             class="filament-forms-section-component rounded-xl border border-gray-300 bg-white">
            <div
                class="filament-forms-section-header-wrapper flex overflow-hidden rounded-t-xl rtl:space-x-reverse min-h-[56px] items-center bg-gray-100 px-4 py-2">
                <div class="filament-forms-section-header flex-1 space-y-1">
                    <h3 class="pointer-events-none flex flex-row items-center font-bold tracking-tight text-xl">
                        Bestelde producten
                    </h3>
                </div>
            </div>
            <div class="filament-forms-section-content-wrapper">
                <div class="filament-forms-section-content p-6 grid gap-4">
                    @foreach($record->orderProducts as $orderProduct)
                        <div class="grid grid-cols-12 gap-4">
                            <div class="flex items-center space-x-4 col-span-12 lg:col-span-6">
                                @if($orderProduct->product && $orderProduct->product->firstImageUrl)
                                    <x-drift::image
                                        config="dashed"
                                        :path="$orderProduct->product->firstImageUrl"
                                        :manipulations="[
                                                'widen' => 100,
                                            ]"
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
            </div>
        </div>
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div class="hidden hidden-for-glitch">
            <livewire:change-order-fulfillment-status
                :order="$record"></livewire:change-order-fulfillment-status>
        </div>
        {{--@foreach(ecommerce()->widgets('orders') as $widget)--}}
        {{--    @if($widget['width'] == 'full')--}}
        {{--        <hr>--}}
        {{--        <livewire:is :component="$widget['name']" :order="$record"></livewire:is>--}}
        {{--    @endif--}}
        {{--@endforeach--}}

        @if(!$record->credit_for_order_id)
            <div>
                <livewire:change-order-fulfillment-status
                    :order="$record"></livewire:change-order-fulfillment-status>
            </div>
            {{--    @if(($record->status == 'paid' || $record->status == 'waiting_for_confirmation' || $record->status == 'partially_paid') && $record->order_origin == 'own')--}}
            {{--        <a href="{{ route('filament.resources.orders.cancel', [$record]) }}"--}}
            {{--           class="inline-flex items-center justify-center font-medium tracking-tight rounded-lg focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 h-9 px-4 text-white shadow focus:ring-white w-full mt-2 w-full">--}}
            {{--            Annuleer bestelling--}}
            {{--        </a>--}}
            {{--        <hr>--}}
            {{--    @elseif(($record->status == 'paid' || $record->status == 'waiting_for_confirmation' || $record->status == 'partially_paid') && $record->order_origin != 'own')--}}
            {{--        <a href="{{ route('filament.resources.orders.cancel', [$record]) }}"--}}
            {{--           class="inline-flex items-center justify-center font-medium tracking-tight rounded-lg focus:outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700 h-9 px-4 text-white shadow focus:ring-white w-full mt-2 w-full">--}}
            {{--            Annuleer bestelling--}}
            {{--        </a>--}}
            {{--        <hr>--}}
            {{--    @endif--}}
            <div>
                <livewire:send-order-confirmation-to-email
                    :order="$record"></livewire:send-order-confirmation-to-email>
            </div>
        @else
            <div>
                <livewire:change-order-retour-status
                    :order="$record"></livewire:change-order-retour-status>
            </div>
        @endif

        {{--    @foreach(ecommerce()->widgets('orders') as $widget)--}}
        {{--        @if($widget['width'] == 'sidebar')--}}
        {{--            <livewire:is :component="$widget['name']" :order="$record"></livewire:is>--}}
        {{--        @endif--}}
        {{--    @endforeach--}}
        <livewire:create-order-log :order="$record"></livewire:create-order-log>

    </div>

</div>
