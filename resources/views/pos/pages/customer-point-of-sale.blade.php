<div class="relative inline-flex w-full overflow-hidden" wire:poll.1s="refreshDataFromDatabase">
    <div
        class="absolute transitiona-all duration-1000 opacity-70 -inset-px bg-gradient-to-r from-[#44BCFF] via-[#FF44EC] to-[#FF675E] rounded-xl blur-lg group-hover:opacity-100 group-hover:-inset-1 group-hover:duration-200 animate-tilt">
    </div>
    <div class="p-8 m-8 border border-white rounded-lg h-[calc(100%) - 50px] overflow-hidden bg-black/90 z-10 w-full">
        <div class="grid divide-x divide-gray-500">
            <div class="sm:pl-8 flex flex-col gap-8">
                <div class="flex flex-col gap-8">
                    <div class="flex flex-wrap justify-between items-center">
                        <p class="text-5xl font-bold">Winkelwagen</p>
                        <div class="flex gap-4">
                            @if(count($products ?: []))
                                <button wire:click="clearProducts"
                                        class="h-12 w-12 bg-red-500 text-white hover:bg-red-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                         stroke-width="1.5"
                                         stroke="currentColor" class="size-6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                    </svg>
                                </button>
                            @endif
                            {{--                            <button id="exitFullscreenBtn"--}}
                            {{--                                    class="@if(!$fullscreen) hidden @endif h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">--}}
                            {{--                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"--}}
                            {{--                                     stroke-width="1.5" stroke="currentColor" class="size-6">--}}
                            {{--                                    <path stroke-linecap="round" stroke-linejoin="round"--}}
                            {{--                                          d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"/>--}}
                            {{--                                </svg>--}}
                            {{--                            </button>--}}
                            {{--                            <button id="fullscreenBtn"--}}
                            {{--                                    class="@if($fullscreen) hidden @endif h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">--}}
                            {{--                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"--}}
                            {{--                                     stroke-width="1.5" stroke="currentColor" class="size-6">--}}
                            {{--                                    <path stroke-linecap="round" stroke-linejoin="round"--}}
                            {{--                                          d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>--}}
                            {{--                                </svg>--}}
                            {{--                            </button>--}}
                        </div>
                    </div>
                    {{--                <div class="p-4 rounded-lg border border-gray-400 grid gap-4">--}}
                    <div
                        class="p-4 rounded-lg border border-gray-400 flex flex-col gap-4 h-[calc(100vh-450px)] overflow-y-auto">
                        @if(count($products ?: []))
                            @foreach($products as $product)
                                @if(!$loop->first)
                                    <hr class="bg-gray-400">
                                @endif
                                <div class="flex flex-wrap items-center gap-4">
                                    <div class="relative">
                                        @if($product['product'] ?? false)
                                            <x-dashed-files::image
                                                class="object-cover rounded-lg w-20 h-20"
                                                :mediaId="$product['product']['firstImage']"/>
                                        @else
                                            <img
                                                src="https://placehold.co/400x400/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}/fff?text=Aangepaste%20verkoop"
                                                class="object-cover rounded-lg w-20 h-20">
                                        @endif
                                        <span
                                            class="bg-primary-500 text-white font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-white">{{ $product['quantity'] }}</span>
                                    </div>
                                    <div class="flex flex-col flex-wrap gap-1">
                                        <span
                                            class="font-bold text-lg">{{ $product['product']['name'] ?? $product['name'] }}</span>
                                    </div>
                                    <div class="ml-auto">
                                        <span
                                            class="font-bold">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($product['price']) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <p>Geen producten geselecteerd...</p>
                        @endif
                    </div>
                </div>
                <div class="mt-auto flex-1 gap-4 grid">
                    <div class="grid gap-2 p-4 rounded-lg border border-gray-400">
                    <span class="text-xl font-bold flex justify-between items-center">
                    <span class="flex flex-col">
                        <span>Totaal</span>
                        <span class="text-sm font-normal">{{ collect($products)->sum('quantity') }} artikelen</span>
                    </span>
                    <span class="font-bold">{{ $total }}</span>
                        @if($activeDiscountCode)
                </span>
                        <hr/>
                        <span class="text-sm font-bold flex justify-between items-center">
                        <span>Korting</span>
                    <span class="font-bold">{{ $discount }}</span>
                            @endif
                </span>
                        {{--                        <hr/>--}}
                        {{--                        <span class="text-sm font-bold flex justify-between items-center">--}}
                        {{--                        <span>Subtotaal</span>--}}
                        {{--                    <span class="font-bold">{{ $subTotal }}</span>--}}
                        {{--                </span>--}}
                        @foreach($vatPercentages as $percentage => $value)
                            @if($loop->first)
                                <hr/>
                            @endif
                            <span class="text-sm font-bold flex justify-between items-center">
                        <span>BTW {{ number_format($percentage, 0) }}%</span>
                    <span class="font-bold">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($value) }}</span>
                </span>
                        @endforeach
                        @if(!count($vatPercentages))
                            <hr/>
                        @endif
                        @if(count($vatPercentages) > 1)
                            <span class="text-sm font-bold flex justify-between items-center">
                        <span>BTW</span>
                    <span class="font-bold">{{ $vat }}</span>
                            @endif
                </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{--    @if($checkoutPopup)--}}
    {{--        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">--}}
    {{--            <div class="absolute h-full w-full" wire:click="closeCheckout"></div>--}}
    {{--            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">--}}
    {{--                <div class="bg-white rounded-lg p-8 grid gap-4">--}}
    {{--                    <div class="absolute top-2 right-2 text-black cursor-pointer"--}}
    {{--                         wire:click="closeCheckout">--}}
    {{--                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"--}}
    {{--                             stroke="currentColor" class="size-10">--}}
    {{--                            <path stroke-linecap="round" stroke-linejoin="round"--}}
    {{--                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>--}}
    {{--                        </svg>--}}
    {{--                    </div>--}}
    {{--                    <div>--}}
    {{--                        <p class="text-3xl font-bold">Totaal: {{ $total }}</p>--}}
    {{--                        <p class="text-xl text-gray-400">Selecteer betalingsoptie</p>--}}
    {{--                    </div>--}}
    {{--                    @if(count($posPaymentMethods))--}}
    {{--                        <div class="grid gap-8">--}}
    {{--                            @foreach($posPaymentMethods as $paymentMethod)--}}
    {{--                                <button wire:click="selectPaymentMethod('{{ $paymentMethod['id'] }}')"--}}
    {{--                                        class="p-4 text-2xl uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full flex items-center flex-wrap justify-between">--}}
    {{--                                    @if($paymentMethod['image'])--}}
    {{--                                        <img--}}
    {{--                                            src="{{ mediaHelper()->getSingleMedia($paymentMethod['image'], 'original')->url ?? '' }}"--}}
    {{--                                            class="h-20 mr-2">--}}
    {{--                                    @else--}}
    {{--                                        <img--}}
    {{--                                            src="https://placehold.co/400x400/000/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}?text={{ $paymentMethod['name'] }}"--}}
    {{--                                            class="object-cover rounded-lg h-20">--}}
    {{--                                    @endif--}}
    {{--                                    <span>{{ $paymentMethod['name'] }}</span>--}}
    {{--                                </button>--}}
    {{--                            @endforeach--}}
    {{--                        </div>--}}
    {{--                    @else--}}
    {{--                        <div class="p-4">--}}
    {{--                            <p class="text-center text-black">Geen betaalmethodes gevonden</p>--}}
    {{--                        </div>--}}
    {{--                    @endif--}}
    {{--                </div>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    @endif--}}
    {{--    @if($paymentPopup)--}}
    {{--        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">--}}
    {{--            <div class="absolute h-full w-full" wire:click="closePayment"></div>--}}
    {{--            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">--}}
    {{--                <div class="bg-white rounded-lg p-8 grid gap-4">--}}
    {{--                    <div class="absolute top-2 right-2 text-black cursor-pointer"--}}
    {{--                         wire:click="closePayment">--}}
    {{--                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"--}}
    {{--                             stroke="currentColor" class="size-10">--}}
    {{--                            <path stroke-linecap="round" stroke-linejoin="round"--}}
    {{--                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>--}}
    {{--                        </svg>--}}
    {{--                    </div>--}}
    {{--                    <div>--}}
    {{--                        <p class="text-3xl font-bold">Totaal: {{ $total }}</p>--}}
    {{--                        <p class="text-xl text-gray-400">Betaalmethode: {{ $paymentMethod->name }}</p>--}}
    {{--                    </div>--}}
    {{--                    @if($paymentMethod->is_cash_payment)--}}
    {{--                        <div class="flex flex-col gap-4">--}}
    {{--                            <div class="flex flex-col gap-2">--}}
    {{--                                <p class="text-xl font-bold">Ontvangen bedrag</p>--}}
    {{--                                <div class="grid md:grid-cols-2 gap-4">--}}
    {{--                                    @foreach($suggestedCashPaymentAmounts as $suggestedCashPaymentAmount)--}}
    {{--                                        <button wire:click="setCashPaymentAmount({{ $suggestedCashPaymentAmount }})"--}}
    {{--                                                class="p-4 text-2xl uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
    {{--                                            {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($suggestedCashPaymentAmount) }}--}}
    {{--                                        </button>--}}
    {{--                                    @endforeach--}}
    {{--                                </div>--}}
    {{--                            </div>--}}
    {{--                            <form wire:submit.prevent="markAsPaid">--}}
    {{--                                {{ $this->cashPaymentForm }}--}}
    {{--                                --}}{{--                                <input wire:model="cashPaymentAmount"--}}
    {{--                                --}}{{--                                       type="number"--}}
    {{--                                --}}{{--                                       min="0"--}}
    {{--                                --}}{{--                                       max="100000"--}}
    {{--                                --}}{{--                                       required--}}
    {{--                                --}}{{--                                       class="text-black w-full rounded-lg pl-4 pr-4"--}}
    {{--                                --}}{{--                                       placeholder="Anders...">--}}
    {{--                            </form>--}}
    {{--                        </div>--}}
    {{--                    @endif--}}
    {{--                    <div class="grid md:grid-cols-2 gap-4 mt-16">--}}
    {{--                        <button wire:click="closePayment"--}}
    {{--                                class="px-4 py-4 text-lg uppercase rounded-lg bg-red-500 hover:bg-red-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
    {{--                            Annuleren--}}
    {{--                        </button>--}}
    {{--                        <button wire:click="markAsPaid"--}}
    {{--                                class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
    {{--                            Markeer als betaald--}}
    {{--                        </button>--}}
    {{--                    </div>--}}
    {{--                </div>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    @endif--}}
    {{--    @if($orderConfirmationPopup)--}}
    {{--        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 text-black">--}}
    {{--            <div class="absolute h-full w-full" wire:click="closeOrderConfirmation"></div>--}}
    {{--            <div class="bg-white rounded-lg p-8 grid gap-4 relative sm:min-w-[800px]">--}}
    {{--                <div class="bg-white rounded-lg p-8 grid gap-4">--}}
    {{--                    <div class="absolute top-2 right-2 text-black cursor-pointer"--}}
    {{--                         wire:click="closeOrderConfirmation">--}}
    {{--                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"--}}
    {{--                             stroke="currentColor" class="size-10">--}}
    {{--                            <path stroke-linecap="round" stroke-linejoin="round"--}}
    {{--                                  d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>--}}
    {{--                        </svg>--}}
    {{--                    </div>--}}
    {{--                    <div>--}}
    {{--                        <p class="text-3xl font-bold">Bestelling {{ $order->invoice_id }} afgerond--}}
    {{--                            - {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->total) }}</p>--}}
    {{--                        <p class="text-xl text-gray-400">--}}
    {{--                            Betaalmethode: {{ $order->orderPayments()->first()->paymentMethod->name }}--}}
    {{--                        </p>--}}
    {{--                    </div>--}}
    {{--                    @if($order->orderPayments()->first()->paymentMethod->is_cash_payment)--}}
    {{--                        <div class="flex flex-col gap-4">--}}
    {{--                            <p class="text-xl font-bold">Betaling overzicht</p>--}}
    {{--                            <div--}}
    {{--                                class="flex flex-wrap items-center justify-between border border-gray-400 rounded-lg p-4 gap-4">--}}
    {{--                                <div class="flex flex-col">--}}
    {{--                                    <p class="font-bold text-lg">Betaling 1</p>--}}
    {{--                                    <p class="text-gray-400">{{ $order->orderPayments()->first()->paymentMethod->name }}</p>--}}
    {{--                                </div>--}}
    {{--                                <div class="flex flex-col">--}}
    {{--                                    <p class="font-bold text-xl">{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->orderPayments()->first()->amount) }}</p>--}}
    {{--                                    @if($order->orderPayments()->first()->amount > $order->total)--}}
    {{--                                        <p class="text-warning-500 font-bold text-xl">--}}
    {{--                                            Wisselgeld--}}
    {{--                                            verschuldigd: {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($order->orderPayments()->first()->amount - $order->total) }}--}}
    {{--                                        </p>--}}
    {{--                                    @endif--}}
    {{--                                </div>--}}
    {{--                            </div>--}}
    {{--                        </div>--}}
    {{--                    @endif--}}
    {{--                    <div class="grid gap-4 mt-16">--}}
    {{--                        <button wire:click="closeOrderConfirmation"--}}
    {{--                                class="px-4 py-4 text-lg uppercase rounded-lg bg-primary-500 hover:bg-primary-700 transition-all ease-in-out duration-300 text-white font-bold w-full">--}}
    {{--                            Terug naar POS--}}
    {{--                        </button>--}}
    {{--                    </div>--}}
    {{--                </div>--}}
    {{--            </div>--}}
    {{--        </div>--}}
    {{--    @endif--}}
    @script
    <script>
        $wire.on('focus', () => {
            document.getElementById("search-product-query").focus();
        });
        $wire.on('focusSearchOrder', () => {
            document.getElementById("searchOrderData.order_id").focus();
        });

        function requestFullscreen() {
            const elem = document.documentElement; // Can be any element, here we use the whole document

            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.mozRequestFullScreen) { // For Firefox
                elem.mozRequestFullScreen();
            } else if (elem.webkitRequestFullscreen) { // For Chrome, Safari, and Opera
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) { // For Internet Explorer/Edge
                elem.msRequestFullscreen();
            }
        }

        function exitFullscreen() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }

        document.addEventListener('fullscreenchange', () => {
            isFullscreen();
        });

        function isFullscreen() {
            var isFullscreen = document.fullscreenElement ||
                document.webkitFullscreenElement ||
                document.mozFullScreenElement ||
                document.msFullscreenElement;
            if (isFullscreen) {
                $wire.dispatch('fullscreenValue', {fullscreen: true});
            } else {
                $wire.dispatch('fullscreenValue', {fullscreen: false});
            }
        }

        document.getElementById("fullscreenBtn").addEventListener("click", function () {
            requestFullscreen();
        });
        document.getElementById("exitFullscreenBtn").addEventListener("click", function () {
            exitFullscreen();
        });
    </script>
    @endscript
</div>
