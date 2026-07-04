{{--
    MODERNE POS-LAYOUT — werkende variant.
    Hergebruikt exact dezelfde POSData()-logica en modals als de klassieke layout
    (zie pos/partials/pos-script.blade.php en pos/partials/pos-modals.blade.php).
    Alleen de "shell" (topbar, zoeken, actie-toolbar, winkelwagen, totalen) is
    opnieuw vormgegeven. Schakelen tussen klassiek/modern gebeurt in POS-instellingen
    (Customsetting 'pos_layout'); de keuze wordt afgehandeld in POSPage2::render().
--}}
@php($primaryHex = str(collect(collect(filament()->getPanels())->first()->getColors())->first())->replace('#', ''))
@php($conceptCount = \Dashed\DashedEcommerceCore\Models\Order::concept()->count())
<div class="relative w-full h-full"
     x-data="POSData()"
     @price-mode-toggled.window="retrieveCart()">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .scrollbar-wide::-webkit-scrollbar { width: 14px; }
        .pos-tool:disabled { opacity: .5; cursor: not-allowed; }
    </style>

    <div class="h-full w-full flex flex-col bg-neutral-100 text-neutral-900 dark:bg-neutral-950 dark:text-white overflow-hidden">

        {{-- ============== TOPBAR ============== --}}
        <header class="shrink-0 h-16 px-5 flex items-center justify-between border-b border-neutral-200 dark:border-white/10 bg-black/[0.03] dark:bg-white/[0.03]">
            <div class="flex items-center gap-4 min-w-0">
                <div class="h-9 w-9 shrink-0 rounded-lg bg-primary-500 grid place-items-center font-black text-white uppercase">{{ str(Customsetting::get('site_name'))->substr(0, 1) }}</div>
                <p class="font-bold text-lg truncate">{{ Customsetting::get('site_name') }}</p>
                <span class="ml-1 px-3 h-9 hidden sm:grid place-items-center rounded-lg bg-neutral-100 dark:bg-white/5 font-mono font-semibold tabular-nums text-lg" x-html="time"></span>
            </div>

            <div class="flex items-center gap-2">
                {{-- Opslaan als concept --}}
                <button type="button" x-cloak x-show="products.length"
                        wire:click="mountAction('saveAsConceptAction')" x-bind:disabled="loading"
                        title="{{ __('Opslaan als concept') }}"
                        class="pos-tool size-10 rounded-lg bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 grid place-items-center transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                </button>
                {{-- Laatste bon printen --}}
                <button x-cloak x-show="lastOrder" @click="printLastOrder" x-bind:disabled="loading"
                        title="Laatste bon printen"
                        class="pos-tool size-10 rounded-lg bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 grid place-items-center transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9.75h4.875a2.625 2.625 0 0 1 0 5.25H12M8.25 9.75 10.5 7.5M8.25 9.75 10.5 12m9-7.243V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185Z"/></svg>
                </button>
                {{-- Producten verversen --}}
                <button @click="refreshProducts()" x-bind:disabled="loading" title="Producten opnieuw ophalen"
                        class="pos-tool size-10 rounded-lg bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 grid place-items-center transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </button>
                {{-- Feest --}}
                <button data-emojis="🔥 💥 🎉 🥳 💸 ✨ 🚀 😎 🌈 🪩 🧨 👑 💃 🕺 🍾 🎊"
                        x-data x-on:click="() => launchEmojis($el)" x-bind:disabled="loading"
                        class="pos-tool size-10 rounded-lg bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 grid place-items-center transition-colors text-lg">🥳</button>
                {{-- Winkelwagen legen --}}
                <button x-cloak x-show="products.length" @click="clearProducts" x-bind:disabled="loading"
                        title="Winkelwagen legen"
                        class="pos-tool size-10 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 grid place-items-center transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                </button>
                <div class="w-px h-7 bg-neutral-200 dark:bg-white/10 mx-1"></div>
                {{-- Light/dark thema wisselen --}}
                <button type="button"
                        @click="$dispatch('theme-changed', ($store.theme === 'dark' ? 'light' : 'dark'))"
                        title="Licht/donker thema wisselen" x-bind:disabled="loading"
                        class="pos-tool size-10 rounded-lg bg-neutral-200 hover:bg-neutral-300 dark:bg-white/5 dark:hover:bg-white/10 grid place-items-center transition-colors">
                    <svg x-cloak x-show="$store.theme === 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z"/></svg>
                    <svg x-show="$store.theme !== 'dark'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"/></svg>
                </button>
                {{-- Volledig scherm --}}
                <button id="fullscreenBtn" @click="toggleFullscreen" x-show="!isFullscreen" x-bind:disabled="loading"
                        title="Volledig scherm"
                        class="pos-tool size-10 rounded-lg bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 grid place-items-center transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                </button>
                <button id="exitFullscreenBtn" @click="toggleFullscreen" x-show="isFullscreen" x-cloak x-bind:disabled="loading"
                        title="Volledig scherm verlaten"
                        class="pos-tool size-10 rounded-lg bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 grid place-items-center transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 9V4.5M9 9H4.5M9 9 3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5 5.25 5.25"/></svg>
                </button>
            </div>
        </header>

        {{-- ============== HOOFD ============== --}}
        <div class="flex-1 min-h-0 flex">

            {{-- -------- LINKS: zoeken + acties + resultaten -------- --}}
            <section class="flex-1 min-w-0 flex flex-col p-5 gap-4">

                {{-- Zoekbalk --}}
                <form @submit.prevent="selectProduct" class="shrink-0">
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-neutral-500 dark:text-white/40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        </span>
                        <input autofocus x-model="searchProductQuery" id="search-product-query"
                               wire:loading.disabled wire:target="searchProducts"
                               :inputmode="!searchQueryInputmode ? 'text' : 'none'"
                               x-bind:class="loading ? 'bg-neutral-100 dark:bg-white/5' : 'bg-neutral-200 dark:bg-white/10'"
                               placeholder="Zoek een product op naam, SKU of barcode..."
                               class="w-full h-12 rounded-xl border border-neutral-200 dark:border-white/10 pl-11 pr-12 text-lg text-neutral-900 dark:text-white placeholder:text-neutral-500 dark:placeholder:text-white/40 focus:ring-2 focus:ring-primary-500 outline-none transition-colors">
                        <button type="button" @click="updateSearchQueryInputmode"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-neutral-500 dark:text-white/50 hover:text-neutral-900 dark:hover:text-white">
                            <span x-show="searchQueryInputmode" x-cloak>
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M 20 4 A2 2 0 0 1 22 6"/><path d="M 22 6 L 22 16.41"/><path d="M 7 16 L 16 16"/><path d="M 9.69 4 L 20 4"/><path d="M14 8h.01"/><path d="M18 8h.01"/><path d="m2 2 20 20"/><path d="M20 20H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2"/><path d="M6 8h.01"/><path d="M8 12h.01"/></svg>
                            </span>
                            <span x-show="!searchQueryInputmode" x-cloak>
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 8h.01"/><path d="M12 12h.01"/><path d="M14 8h.01"/><path d="M16 12h.01"/><path d="M18 8h.01"/><path d="M6 8h.01"/><path d="M7 16h10"/><path d="M8 12h.01"/><rect width="20" height="16" x="2" y="4" rx="2"/></svg>
                            </span>
                        </button>
                    </div>
                </form>

                {{-- Actie-toolbar (alleen tonen als er niet gezocht wordt) --}}
                <div class="shrink-0 flex flex-wrap gap-2" x-cloak x-show="!searchProductQuery">
                    {{-- Aangepaste verkoop --}}
                    <button @click="toggle('customProductPopup')" x-bind:disabled="loading"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-400"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        Aangepaste verkoop
                    </button>
                    {{-- Korting toepassen --}}
                    <button @click="toggle('createDiscountPopup')" x-bind:disabled="loading"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-400"><line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                        <span x-text="appliedDiscountCodes.length ? 'Korting (' + appliedDiscountCodes.length + ')' : 'Korting'">Korting</span>
                    </button>
                    {{-- Korting verwijderen (legacy losse korting) --}}
                    <button @click="removeDiscount" x-bind:disabled="loading"
                            x-cloak x-show="activeDiscountCode && !appliedDiscountCodes.length"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 text-red-300 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
                        Korting verwijderen
                    </button>
                    {{-- Cadeaubon --}}
                    <button @click="toggle('redeemGiftCardPopup')" x-bind:disabled="loading"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-400"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13"/><path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5"/></svg>
                        <span x-text="giftCards.length ? 'Cadeaubon (' + giftCards.length + ')' : 'Cadeaubon'">Cadeaubon</span>
                    </button>
                    {{-- Klantgegevens --}}
                    <button @click="toggle('customerDataPopup')" x-bind:disabled="loading"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[18px] text-primary-400"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                        Klant
                    </button>
                    {{-- Verzendmethode toepassen --}}
                    <button @click="toggle('chooseShippingMethodPopup')" x-bind:disabled="loading"
                            x-cloak x-show="!shippingMethodId"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[18px] text-primary-400"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        Verzending
                    </button>
                    {{-- Verzendmethode verwijderen --}}
                    <button @click="removeShippingMethod" x-bind:disabled="loading"
                            x-cloak x-show="shippingMethodId"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 text-red-300 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[18px]"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        Verzending verwijderen
                    </button>
                    {{-- Kassalade openen --}}
                    <button @click="openCashRegister" x-bind:disabled="loading"
                            x-cloak x-show="hasCashRegister"
                            class="pos-tool h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-400"><path d="M11 15h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 17"/><path d="m7 21 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 16 6 6"/><circle cx="16" cy="9" r="2.9"/><circle cx="6" cy="5" r="3"/></svg>
                        Kassalade
                    </button>
                    {{-- Zoek bestelling --}}
                    <button @click="showOrdersPopup()" x-bind:disabled="loading"
                            class="pos-tool focus-search-order h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[18px] text-primary-400"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        Zoek bestelling
                    </button>
                    {{-- Voorraadbeheer --}}
                    <button @click="showStockPopup()" x-bind:disabled="loading"
                            class="pos-tool focus-search-order h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[18px] text-primary-400"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                        Voorraad
                    </button>
                    {{-- BTW-weergave wisselen --}}
                    <button type="button" wire:click="togglePriceMode" x-bind:disabled="loading"
                            x-bind:title="isExVat ? 'Klik om incl BTW te tonen' : 'Klik om ex BTW te tonen'"
                            class="pos-tool focus-search-order h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[18px] text-primary-400"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 6h.008v.008h-.008V15zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                        <span x-text="isExVat ? 'Incl. BTW tonen' : 'Ex. BTW tonen'"></span>
                    </button>
                    {{-- Concepten --}}
                    @if ($conceptCount > 0)
                        <button type="button" wire:click="mountAction('conceptQueueAction')" x-bind:disabled="loading"
                                class="pos-tool focus-search-order h-11 px-3.5 rounded-xl bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 flex items-center gap-2 text-sm font-medium transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-[18px] text-primary-400"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/></svg>
                            {{ __('Concepten') }} ({{ $conceptCount }})
                        </button>
                    @endif
                </div>

                {{-- Resultaten / lege staat --}}
                <div class="flex-1 min-h-0 overflow-y-auto -mx-1 px-1 scrollbar-wide">
                    {{-- Zoekresultaten --}}
                    <div x-cloak x-show="!loadingSearchedProducts && searchProductQuery && searchedProducts.length"
                         class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                        <template x-for="product in searchedProducts" :key="product.id">
                            <button @click="addProduct(product.id)"
                                    class="text-left rounded-2xl p-3 bg-neutral-100 dark:bg-white/5 hover:bg-neutral-200 dark:hover:bg-white/10 border border-neutral-200 dark:border-white/10 hover:border-primary-500 transition-all flex flex-col gap-3">
                                <div class="rounded-xl overflow-hidden bg-neutral-100 dark:bg-white/5 aspect-[4/3] grid place-items-center" x-show="product.image">
                                    <img :src="product.image" class="object-cover w-full h-full">
                                </div>
                                <div class="rounded-xl bg-neutral-100 dark:bg-white/5 aspect-[4/3] grid place-items-center text-neutral-500 dark:text-white/20" x-show="!product.image">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="size-10"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/></svg>
                                </div>
                                <div class="leading-tight">
                                    <p class="font-semibold text-sm line-clamp-2" x-html="product.name"></p>
                                    <p class="font-bold text-base mt-1" x-html="product.currentPrice"></p>
                                    <p x-show="product.actual_stock >= 3" class="text-xs font-medium mt-0.5 text-green-400" x-html="product.actual_stock + ' op voorraad'"></p>
                                    <p x-show="product.actual_stock < 3 && product.actual_stock > 0" class="text-xs font-medium mt-0.5 text-orange-400" x-html="product.actual_stock + ' op voorraad'"></p>
                                    <p x-show="product.actual_stock < 1 && product.stock > 0" class="text-xs font-medium mt-0.5 text-orange-400">op nabestelling</p>
                                    <p x-show="product.actual_stock < 1 && product.stock < 1" class="text-xs font-medium mt-0.5 text-red-600 dark:text-red-400">uitverkocht</p>
                                </div>
                            </button>
                        </template>
                    </div>
                    {{-- Geen resultaten --}}
                    <div x-cloak x-show="!loadingSearchedProducts && searchProductQuery && !searchedProducts.length"
                         class="h-full grid place-items-center text-center text-neutral-500 dark:text-white/40">
                        <div>
                            <p x-show="searchProductQuery.length >= 3" class="text-lg">Geen producten gevonden</p>
                            <p x-show="searchProductQuery.length < 3" class="text-lg">Vul een zoekterm in...</p>
                        </div>
                    </div>
                    {{-- Laden --}}
                    <div x-cloak x-show="loadingSearchedProducts" class="h-full grid place-items-center text-center text-neutral-500 dark:text-white/40">
                        <p class="text-lg">Producten aan het laden...</p>
                    </div>
                </div>
            </section>

            {{-- -------- RECHTS: winkelwagen + afrekenen -------- --}}
            <aside class="w-[400px] shrink-0 flex flex-col border-l border-neutral-200 dark:border-white/10 bg-black/[0.03] dark:bg-white/[0.03]">

                {{-- Kop --}}
                <div class="shrink-0 h-16 px-5 flex items-center justify-between border-b border-neutral-200 dark:border-white/10">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary-400"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        <span class="font-bold text-lg">Bestelling</span>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-neutral-200 dark:bg-white/10 text-neutral-500 dark:text-white/70" x-html="totalQuantity() + ' artikelen'">0 artikelen</span>
                    </div>
                    <button x-cloak x-show="products.length" @click="clearProducts" x-bind:disabled="loading"
                            class="pos-tool text-sm text-neutral-500 dark:text-white/50 hover:text-red-600 dark:hover:text-red-400 font-medium flex items-center gap-1 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79"/></svg>
                        Legen
                    </button>
                </div>

                {{-- Winkelwagen-regels --}}
                <div class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2 scrollbar-wide">
                    <template x-for="product in products" :key="product.identifier">
                        <div class="rounded-xl bg-neutral-100 dark:bg-white/5 border border-neutral-200 dark:border-white/10 p-3 flex gap-3">
                            <div class="relative shrink-0">
                                <img :src="product.image" x-cloak x-show="product.image" class="object-cover rounded-lg w-16 h-16">
                                <img x-cloak x-show="!product.image && product.customProduct === true"
                                     src="https://placehold.co/400x400/{{ $primaryHex }}/fff?text=Aangepaste%20verkoop"
                                     class="object-cover rounded-lg w-16 h-16">
                                <img x-cloak x-show="!product.image && product.customProduct !== true"
                                     :src="'https://placehold.co/400x400/{{ $primaryHex }}/fff?text=' + product.name"
                                     class="object-cover rounded-lg w-16 h-16">
                                <span class="bg-primary-500 text-white text-xs font-bold rounded-full w-6 h-6 absolute -right-2 -top-2 flex items-center justify-center border-2 border-neutral-100 dark:border-neutral-950" x-html="product.quantity"></span>
                            </div>
                            <div class="flex-1 min-w-0 flex flex-col gap-1.5">
                                <span class="text-sm leading-tight" x-html="product.name"></span>
                                <div class="leading-tight">
                                    <span class="font-bold text-sm" x-html="product.priceFormattedPrimary ?? product.priceFormatted"></span>
                                    <span class="text-xs text-neutral-500 dark:text-white/40 ml-1" x-html="product.priceFormattedSecondary" x-show="product.priceFormattedSecondary"></span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-auto">
                                    <div class="flex items-center bg-neutral-200 dark:bg-white/10 rounded-lg">
                                        <button @click="changeQuantity(product.identifier, product.quantity - 1)" x-bind:disabled="loading"
                                                class="pos-tool size-8 grid place-items-center rounded-l-lg hover:bg-primary-500 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                        </button>
                                        <span class="w-7 text-center font-bold tabular-nums text-sm" x-html="product.quantity"></span>
                                        <button @click="changeQuantity(product.identifier, product.quantity + 1)" x-bind:disabled="loading"
                                                class="pos-tool size-8 grid place-items-center rounded-r-lg hover:bg-primary-500 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                        </button>
                                    </div>
                                    <button @click="openChangeProductPricePopup(product)" x-bind:disabled="loading"
                                            title="Prijs aanpassen"
                                            class="pos-tool size-8 grid place-items-center rounded-lg bg-orange-500/15 text-orange-300 hover:bg-orange-500/30 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                    </button>
                                    <button @click="changeQuantity(product.identifier, 0)" x-bind:disabled="loading"
                                            title="Verwijderen"
                                            class="pos-tool size-8 grid place-items-center rounded-lg bg-red-500/15 text-red-300 hover:bg-red-500/30 transition-colors ml-auto">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div x-show="products.length === 0" class="h-full grid place-items-center text-center text-neutral-500 dark:text-white/30 py-10">
                        <div>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="size-12 mx-auto mb-2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                            <p>Nog geen producten geselecteerd</p>
                        </div>
                    </div>
                </div>

                {{-- Totalen + afrekenen --}}
                <div class="shrink-0 border-t border-neutral-200 dark:border-white/10 p-4 space-y-3">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="font-bold" x-text="isExVat ? 'Subtotaal ex BTW' : 'Subtotaal'"></span>
                                <span class="text-xs text-neutral-500 dark:text-white/50" x-html="totalQuantity() + ' artikelen'">0 artikelen</span>
                            </div>
                            <span class="font-bold text-lg" x-html="subTotal"></span>
                        </div>

                        {{-- Kortingscodes --}}
                        <div x-show="appliedDiscountCodes.length > 0" x-cloak class="space-y-1 border-t border-neutral-200 dark:border-white/10 pt-2">
                            <template x-for="code in appliedDiscountCodes" :key="code.code">
                                <div class="text-sm flex justify-between items-center gap-2">
                                    <span class="flex items-center gap-2 truncate text-neutral-500 dark:text-white/70">
                                        <button type="button" @click="$wire.removeDiscountCode(code.code)" class="text-red-600 dark:text-red-400 hover:text-red-300 shrink-0" title="Kortingscode verwijderen">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                        <span class="truncate">Korting <span x-text="code.code" class="font-mono"></span> (<span x-text="code.valueLabel"></span>)</span>
                                    </span>
                                    <span class="font-semibold whitespace-nowrap text-primary-400">- <span x-html="code.appliedAmountFormatted"></span></span>
                                </div>
                            </template>
                            <div class="text-sm flex justify-between items-center font-semibold" x-show="appliedDiscountCodes.length > 1">
                                <span class="text-neutral-500 dark:text-white/70">Kortingen totaal</span>
                                <span class="text-primary-400" x-html="discount"></span>
                            </div>
                        </div>
                        <div x-show="activeDiscountCode && appliedDiscountCodes.length === 0" x-cloak class="text-sm flex justify-between items-center border-t border-neutral-200 dark:border-white/10 pt-2">
                            <span class="text-neutral-500 dark:text-white/70">Korting</span>
                            <span class="font-semibold text-primary-400" x-html="discount"></span>
                        </div>

                        {{-- Cadeaubonnen --}}
                        <div x-show="giftCards.length > 0" x-cloak class="space-y-1 border-t border-neutral-200 dark:border-white/10 pt-2">
                            <template x-for="card in giftCards" :key="card.code">
                                <div class="text-sm flex justify-between items-center gap-2">
                                    <span class="flex items-center gap-2 truncate text-neutral-500 dark:text-white/70">
                                        <button type="button" @click="$wire.removeGiftCardCode(card.code)" class="text-red-600 dark:text-red-400 hover:text-red-300 shrink-0" title="Cadeaubon verwijderen">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                        </button>
                                        <span class="truncate">Cadeaubon <span x-text="card.code" class="font-mono"></span></span>
                                    </span>
                                    <span class="font-semibold whitespace-nowrap text-primary-400">- <span x-html="card.redeemedFormatted"></span></span>
                                </div>
                            </template>
                            <div class="text-sm flex justify-between items-center font-semibold" x-show="giftCards.length > 1">
                                <span class="text-neutral-500 dark:text-white/70">Cadeaubonnen totaal</span>
                                <span class="text-primary-400">- <span x-html="giftCardsTotal"></span></span>
                            </div>
                        </div>

                        {{-- Verzendkosten --}}
                        <div x-show="shippingMethodId" x-cloak class="text-sm flex justify-between items-center border-t border-neutral-200 dark:border-white/10 pt-2">
                            <span class="text-neutral-500 dark:text-white/70">Verzendkosten</span>
                            <span class="font-semibold" x-html="shippingMethodCosts"></span>
                        </div>

                        {{-- BTW --}}
                        <div class="border-t border-neutral-200 dark:border-white/10 pt-2 space-y-1">
                            <template x-for="value,percentage in vatPercentages" x-show="vatPercentages.length">
                                <div class="text-sm flex justify-between items-center text-neutral-500 dark:text-white/70">
                                    <span x-html="'BTW ' + percentage + '%'"></span>
                                    <span x-html="value"></span>
                                </div>
                            </template>
                            <div x-cloak x-show="vatPercentages.length > 1" class="text-sm flex justify-between items-center font-semibold">
                                <span>BTW</span>
                                <span x-html="vat"></span>
                            </div>
                            <div x-show="vatPercentages.length == 0" class="text-sm flex justify-between items-center text-neutral-500 dark:text-white/70">
                                <span>BTW</span><span>0</span>
                            </div>
                        </div>

                        {{-- Totaal incl (alleen in ex-BTW modus) --}}
                        <template x-if="isExVat">
                            <div class="flex items-center justify-between border-t border-neutral-200 dark:border-white/10 pt-2 font-bold">
                                <span>Totaal incl BTW</span>
                                <span x-html="subTotalIncl"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Afrekenknop --}}
                    <button @click="(totalUnformatted !== null && totalUnformatted <= 0) ? selectPaymentMethod(null) : toggle('checkoutPopup')"
                            x-bind:disabled="loading || products.length === 0"
                            x-bind:class="(loading || products.length === 0) ? 'bg-primary-900 cursor-not-allowed' : 'bg-primary-500 hover:bg-primary-600'"
                            class="w-full h-14 rounded-xl text-white font-bold text-lg flex items-center justify-center gap-2 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                        <span x-text="(totalUnformatted !== null && totalUnformatted <= 0) ? 'Afronden' : 'Betaal'">Betaal</span>
                        <span x-html="total">€0,-</span>
                    </button>
                </div>
            </aside>
        </div>
    </div>

    @include('dashed-ecommerce-core::pos.partials.pos-modals-modern')
@include('dashed-ecommerce-core::pos.partials.pos-script')

<x-filament-actions::modals/>
</div>
