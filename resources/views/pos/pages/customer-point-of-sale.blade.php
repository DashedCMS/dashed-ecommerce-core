<div class="relative w-full h-full"
     x-data="POSData({
        endpoint: @js(route('api.point-of-sale.retrieve-cart-for-customer')),
        fallbackImage: @js('https://placehold.co/400x400/' . str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', '') . '/fff?text=Aangepaste%20verkoop')
     })"
     x-init="init()">

    <div class="absolute top-4 right-4 z-20 flex gap-4">
        <button id="fullscreenBtn" @click="toggleFullscreen"
                x-show="!isFullscreen"
                class="h-12 w-12 bg-primary-500 text-white hover:bg-primary-700 transition-all duration-300 ease-in-out p-1 rounded-full flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                 stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/>
            </svg>
        </button>
    </div>

    <div class="p-8 border-4 border-primary-500 overflow-hidden bg-black/90 z-10 w-full h-full">
        <div class="grid grid-cols-1 divide-x divide-primary-500 h-full">
            <div class="flex flex-col gap-8 overflow-y-auto">
                <div class="flex flex-col gap-8 grow p-4 rounded-lg border border-primary-500 overflow-y-auto gap-4">
                    <template x-for="product in displayedProducts()" :key="product.id ?? product.name">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="relative">
                                <img
                                    :src="product.image || fallbackImage"
                                    class="object-cover rounded-lg w-20 h-20"
                                >
                                <span
                                    class="bg-primary-500 text-white font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-white"
                                    x-text="product.quantity">
                                </span>
                            </div>
                            <div class="flex flex-col flex-wrap gap-1 flex-1">
                                <span class="text-lg word-wrap text-sm" x-text="product.name"></span>
                                <span class="font-bold text-md word-wrap" x-text="product.priceFormatted"></span>
                            </div>
                            <div class="ml-auto">
                                <span class="font-bold">
                                    <!-- eventueel extra prijsinfo -->
                                </span>
                            </div>
                        </div>
                    </template>

                    <p x-show="!products.length">Geen producten geselecteerd...</p>
                </div>

                <div class="mt-auto gap-4 grid">
                    <div class="grid gap-2 p-4 rounded-lg border border-primary-500">
                        <div class="text-xl font-bold grid gap-2">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span>Subtotaal</span>
                                    <span class="text-sm font-normal" x-text="totalQuantity() + ' artikelen'"></span>
                                </div>
                                <span class="font-bold" x-text="subtotal"></span>
                            </div>
                            <hr/>

                            <template x-if="activeDiscountCode">
                                <div>
                                    <div class="text-sm font-bold flex justify-between items-center mb-2">
                                        <span>Korting</span>
                                        <span class="font-bold" x-text="discount"></span>
                                    </div>
                                    <hr>
                                </div>
                            </template>

                            <template x-for="(value, percentage) in vatPercentages" :key="percentage">
                                <div class="text-sm font-bold flex justify-between items-center">
                                    <span x-text="'BTW ' + percentage + '%'"></span>
                                    <span class="font-bold" x-text="value"></span>
                                </div>
                            </template>

                            <template x-if="Object.keys(vatPercentages).length > 1">
                                <div>
                                    <hr/>
                                    <div class="text-sm font-bold flex justify-between items-center">
                                        <span>BTW</span>
                                        <span class="font-bold" x-text="vat"></span>
                                    </div>
                                </div>
                            </template>

                            <template x-if="Object.keys(vatPercentages).length === 0">
                                <div class="text-sm font-bold flex justify-between items-center">
                                    <span>BTW</span>
                                    <span class="font-bold">0</span>
                                </div>
                            </template>
                        </div>

                        <div class="text-sm font-bold flex justify-between items-center">
                            <span>Totaal</span>
                            <span class="font-bold" x-text="total"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('POSData', (config) => ({
        // config from Blade
        discountCode: null,
        endpoint: config.endpoint,
        fallbackImage: config.fallbackImage,

        // state
        isFullscreen: false,
        pollInterval: null,

        products: [],
        subtotal: '0',
        total: '0',
        discount: '0',
        activeDiscountCode: null,
        vat: '0',
        vatPercentages: {},

        init() {
            this.fetchProducts();
            this.pollInterval = setInterval(() => this.fetchProducts(), 1000);
        },

        displayedProducts() {
            // hetzelfde als array_reverse($products)
            return [...this.products];
        },

        totalQuantity() {
            return this.products.reduce((sum, p) => {
                const qty = Number(p.quantity ?? 0);
                return sum + (isNaN(qty) ? 0 : qty);
            }, 0);
        },

        async fetchProducts() {
            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        // als route in web.php hangt met csrf:
                        // 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        userId: '{{ auth()->user()->id }}',
                    }),
                });

                if (!response.ok) {
                    this.products = [];
                    this.subtotal = '0';
                    this.total = '0';
                    this.discount = '0';
                    this.vat = '0';
                    this.activeDiscountCode = null;
                    this.vatPercentages = {};
                    console.error('POS fetch error', response.status);
                    return;
                }

                const data = await response.json();

                this.products = data.products ?? [];
                this.subtotal = data.subTotal ?? 0; // let op: subTotal
                this.total = data.total ?? 0;
                this.discount = data.discount ?? 0;
                this.vat = data.vat ?? 0;
                this.activeDiscountCode = data.activeDiscountCode ?? null;
                this.vatPercentages = data.vatPercentages ?? {};
            } catch (e) {
                this.products = [];
                this.subtotal = '0';
                this.total = '0';
                this.discount = '0';
                this.vat = '0';
                this.activeDiscountCode = null;
                this.vatPercentages = {};

                console.error('POS fetch exception', e);
            }
        },

        toggleFullscreen() {
            if (!document.fullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.msRequestFullscreen) {
                    document.documentElement.msRequestFullscreen();
                }
                this.isFullscreen = true;

                // als je Livewire eigenschap ook wilt syncen:
                // if (window.Livewire) Livewire.find(@this.__instance.id).call('fullscreenValue', true);
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
                this.isFullscreen = false;
                // idem hierboven voor fullscreenValue(false) als nodig
            }
        },
    }));
</script>
@endscript
