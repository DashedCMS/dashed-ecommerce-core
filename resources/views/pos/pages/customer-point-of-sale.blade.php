<div class="relative w-full h-full"
     wire:poll.1000ms="getProducts()"
     x-data="POSData()">
    <div class="p-8 border-4 border-primary-500 overflow-hidden bg-black/90 z-10 w-full h-full">
        <div class="grid grid-cols-1 divide-x divide-primary-500 h-full">
            <div class="flex flex-col gap-8 overflow-y-auto">
                <div class="flex flex-col gap-8 grow p-4 rounded-lg border border-primary-500 overflow-y-auto gap-4">
                    @foreach($products as $product)
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="relative">
                                @if($product['image'])
                                    <img src="{{ $product['image'] }}"
                                         class="object-cover rounded-lg w-20 h-20">
                                @else
                                    <img
                                        src="https://placehold.co/400x400/{{ str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') }}/fff?text=Aangepaste%20verkoop"
                                        class="object-cover rounded-lg w-20 h-20">
                                @endif
                                <span
                                    class="bg-primary-500 text-white font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-white">
                                {{ $product['quantity'] }}x
                                </span>
                            </div>
                            <div class="flex flex-col flex-wrap gap-1 flex-1">
                                <span class="text-lg word-wrap text-sm">{{ $product['name'] }}</span>
                                <span class="font-bold text-md word-wrap">{{ $product['priceFormatted'] }}</span>
                            </div>
                            <div class="ml-auto">
                                <span class="font-bold">
{{--                                                                            {{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($product['price']) }}--}}
                                </span>
                            </div>
                        </div>
                    @endforeach
                    @if(!count($products))
                        <p>Geen producten geselecteerd...</p>
                    @endif
                </div>
                <div class="mt-auto gap-4 grid">
                    <div class="grid gap-2 p-4 rounded-lg border border-primary-500">
                        <div class="text-xl font-bold grid gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span>Subtotaal</span>
                                    <span class="text-sm font-normal">{{ collect($products)->sum('quantity') }} artikelen</span>
                                </div>
                                <span class="font-bold">{{ $subtotal }}</span>
                            </div>
                            <hr/>
                            @if($activeDiscountCode)
                                <div>
                                    <div class="text-sm font-bold flex justify-between items-center mb-2">
                                        <span>Korting</span>
                                        <span class="font-bold"> {{ $discount }}</span>
                                    </div>
                                    <hr>
                                </div>
                            @endif
                            {{--                        <hr/>--}}
                            {{--                        <span class="text-sm font-bold flex justify-between items-center">--}}
                            {{--                        <span>Subtotaal</span>--}}
                            {{--                    <span class="font-bold">{{ $subTotal }}</span>--}}
                            {{--                </span>--}}
                            @foreach($vatPercentages as $percentage => $value)
                                <div class="text-sm font-bold flex justify-between items-center">
                                    <span> BTW {{ $percentage }}%</span>
                                    <span class="font-bold">{{ $value }}</span>
                                </div>
                            @endforeach
                            @if(count($vatPercentages) > 1)
                                <hr/>
                                <div class="text-sm font-bold flex justify-between items-center">
                                    <span>BTW</span>
                                    <span class="font-bold">{{ $btw }}</span>
                                </div>
                            @elseif(count($vatPercentages) == 0)
                                <div class="text-sm font-bold flex justify-between items-center">
                                    <span>BTW</span>
                                    <span class="font-bold">0</span>
                                </div>
                            @endif
                        </div>
                        <div class="text-sm font-bold flex justify-between items-center">
                            <span>Totaal</span>
                            <span class="font-bold">{{ $total }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@script
<script>
    Alpine.data('POSData', () => ({
        isFullscreen: false,

        toggle(variable) {
            if (variable in this) {
                this[variable] = !this[variable];
            }
        },

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) { // Firefox
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) { // Chrome, Safari and Opera
                    document.documentElement.webkitRequestFullscreen();
                } else if (document.documentElement.msRequestFullscreen) { // IE/Edge
                    document.documentElement.msRequestFullscreen();
                }
                this.isFullscreen = true;
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.mozCancelFullScreen) { // Firefox
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) { // Chrome, Safari and Opera
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) { // IE/Edge
                    document.msExitFullscreen();
                }
                this.isFullscreen = false;
            }
        },
    }));
</script>
@endscript
</div>
