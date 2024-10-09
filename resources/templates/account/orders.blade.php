<x-container>
    <div class="grid lg:grid-cols-6 gap-8 py-16 sm:py-24">
        <div class="lg:col-span-2">
            <nav class="grid space-y-2" aria-label="Tabs">
                <a href="{{AccountHelper::getAccountUrl()}}"
                   class="button button--primary-dark">
                    {{Translation::get('my-account', 'account', 'Mijn account')}}
                </a>
                <a href="{{EcommerceAccountHelper::getAccountOrdersUrl()}}"
                   class="button button--primary-light">
                    {{Translation::get('my-orders', 'account', 'Mijn bestellingen')}}
                </a>
                <a href="{{AccountHelper::getLogoutUrl()}}"
                   class="button button--primary-dark">
                    {{Translation::get('logout', 'login', 'Uitloggen')}}
                </a>
            </nav>
        </div>
        <div class="lg:col-span-4">
            <h1 class="text-2xl">{{Translation::get('my-orders', 'account', 'Mijn bestellingen')}}</h1>
            @if(!$orders->count())
                <p>{{Translation::get('no-orders-yet', 'account', 'Je hebt nog geen bestellingen')}}</p>
            @else
                <div class="space-y-8 lg:px-0 mt-4">
                    @foreach($orders as $order)
                        <div class="border-b border-t border-gray-200 bg-white shadow-sm sm:rounded-lg sm:border">
                            <div
                                class="flex items-center border-b border-gray-200 p-4 sm:grid sm:grid-cols-4 sm:gap-x-6 sm:p-6">
                                <dl class="grid flex-1 grid-cols-2 gap-x-6 text-sm sm:col-span-3 sm:grid-cols-3 lg:col-span-2">
                                    <div>
                                        <dt class="font-medium text-gray-900">{{ Translation::get('order-number', 'orders', 'Bestelnummer') }}</dt>
                                        <dd class="mt-1 text-gray-500">{{ $order->invoice_id }}</dd>
                                    </div>
                                    <div class="hidden sm:block">
                                        <dt class="font-medium text-gray-900">{{ Translation::get('created-at', 'orders', 'Besteld op') }}</dt>
                                        <dd class="mt-1 text-gray-500">
                                            {{ $order->created_at->format('d-m-Y') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-gray-900">{{ Translation::get('total-amount', 'orders', 'Totaal bedrag') }}</dt>
                                        <dd class="mt-1 font-medium text-gray-900">{{ CurrencyHelper::formatPrice($order->total) }}</dd>
                                    </div>
                                </dl>

                                <div class="relative flex justify-end lg:hidden" x-data="{ showOptions: false }">
                                    <div class="flex items-center">
                                        <button type="button"
                                                @click="showOptions = !showOptions"
                                                @click.away="showOptions = false"
                                                @keydown.window.escape="showOptions = false"
                                                class="-m-2 flex items-center p-2 text-gray-400 hover:text-gray-500"
                                                id="menu-0-button" aria-expanded="false" aria-haspopup="true">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                                 stroke="currentColor" aria-hidden="true" data-slot="icon">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                      d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"/>
                                            </svg>
                                        </button>
                                    </div>

                                    <div
                                        class="absolute right-0 z-10 mt-2 w-40 origin-bottom-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                                        x-transition:enter="transition-opacity ease-linear duration-300"
                                        x-transition:enter-start="opacity-0"
                                        x-transition:enter-end="opacity-100"
                                        x-transition:leave="transition-opacity ease-linear duration-300"
                                        x-transition:leave-start="opacity-100"
                                        x-transition:leave-end="opacity-0"
                                        x-show="showOptions"
                                        x-cloak
                                        role="menu" aria-orientation="vertical" aria-labelledby="menu-0-button"
                                        tabindex="-1">
                                        <div class="py-1" role="none">
                                            <a href="{{$order->getUrl()}}"
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                               role="menuitem"
                                               tabindex="-1"
                                               id="menu-0-item-0">{{Translation::get('view-order', 'orders', 'Bekijk bestelling')}}</a>
                                            <a href="{{$order->downloadInvoiceUrl()}}"
                                               class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                               role="menuitem"
                                               tabindex="-1"
                                               id="menu-0-item-1">{{Translation::get('download-invoice', 'orders', 'Download factuur')}}</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="hidden lg:col-span-2 lg:flex lg:items-center lg:justify-end lg:space-x-4">
                                    <a href="{{$order->getUrl()}}"
                                       class="button button--primary-dark">
                                        <span>{{Translation::get('view-order', 'orders', 'Bekijk bestelling')}}</span>
                                    </a>
                                    <a href="{{$order->downloadInvoiceUrl()}}"
                                       class="button button--primary-dark">
                                        <span>{{Translation::get('download-invoice', 'orders', 'Download factuur')}}</span>
                                    </a>
                                </div>
                            </div>

                            <h4 class="sr-only">{{ Translation::get('products', 'orders', 'Producten') }}</h4>
                            <ul role="list" class="divide-y divide-gray-200">
                                @foreach($order->orderProducts as $orderProduct)
                                    <li class="p-4 sm:p-6">
                                        <div class="flex items-center sm:items-start">
                                            <div
                                                class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-lg bg-gray-200 sm:h-40 sm:w-40">
                                                @if($orderProduct->product && $orderProduct->product->firstImage)
                                                    <x-dashed-files::image
                                                        class="h-full w-full object-cover object-center"
                                                        :mediaId="$orderProduct->product->firstImage"
                                                        :manipulations="[
                                                                'widen' => 300,
                                                            ]"
                                                    />
                                                @endif
                                            </div>
                                            <div class="ml-6 flex-1 text-sm">
                                                <div class="font-medium text-gray-900 sm:flex sm:justify-between">
                                                    <h5>{{ $orderProduct->name }}
                                                        @if($orderProduct->product_extras)
                                                            @foreach($orderProduct->product_extras as $option)
                                                                <br>
                                                                <small>{{$option['name']}}: {{$option['value']}}</small>
                                                            @endforeach
                                                        @endif
                                                    </h5>
                                                    <p class="mt-2 sm:mt-0">{{ CurrencyHelper::formatPrice($orderProduct->price) }}</p>
                                                </div>
                                                @if($orderProduct->product && $orderProduct->product->short_description)
                                                    <p class="hidden text-gray-500 sm:mt-2 sm:block">
                                                        {{ str($orderProduct->product->short_description)->excerpt() }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>

                                        @if($orderProduct->product && Product::publicShowable()->where('id', $orderProduct->product->id)->count())
                                            <div class="mt-6 sm:flex sm:justify-between">
                                                <div class="flex items-center">
                                                    @if($orderProduct->product->inStock())
                                                        @if($orderProduct->product->hasDirectSellableStock())
                                                            @if($orderProduct->product->stock() > 10)
                                                                <p class="text-md tracking-wider text-primary-800 flex items-center font-bold"><span
                                                                        class="mr-1"><svg class="w-6 h-6" fill="none"
                                                                                          stroke="currentColor"
                                                                                          viewBox="0 0 24 24"
                                                                                          xmlns="http://www.w3.org/2000/svg"><path
                                                                                stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                                                    {{Translation::get('product-in-stock', 'product', 'Op voorraad')}}
                                                                </p>
                                                            @else
                                                                <p class="text-md tracking-wider text-primary-800 flex items-center font-bold"><span
                                                                        class="mr-1"><svg class="w-6 h-6" fill="none"
                                                                                          stroke="currentColor"
                                                                                          viewBox="0 0 24 24"
                                                                                          xmlns="http://www.w3.org/2000/svg"><path
                                                                                stroke-linecap="round"
                                                                                stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </span>
                                                                    {{Translation::get('product-in-stock-specific', 'product', 'Nog :count: op voorraad', 'text', [
                                            'count' => $orderProduct->product->stock()
                                            ])}}
                                                                </p>
                                                            @endif
                                                        @else
                                                            @if($orderProduct->product->expectedDeliveryInDays())
                                                                <p class="font-bold italic text-md flex items-center gap-1 text-primary-800">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                         viewBox="0 0 24 24" stroke-width="1.5"
                                                                         stroke="currentColor" class="size-6">
                                                                        <path stroke-linecap="round"
                                                                              stroke-linejoin="round"
                                                                              d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                                                    </svg>
                                                                    <span>{{ Translation::get('pre-order-product-static-delivery-time', 'product', 'Levering duurt circa :days: dagen', 'text', [
                                                'days' => $orderProduct->product->expectedDeliveryInDays()
                                            ]) }}</span>
                                                                </p>
                                                            @else
                                                                <p class="font-bold italic text-md flex items-center gap-1 text-primary-800">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none"
                                                                         viewBox="0 0 24 24" stroke-width="1.5"
                                                                         stroke="currentColor" class="size-6">
                                                                        <path stroke-linecap="round"
                                                                              stroke-linejoin="round"
                                                                              d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/>
                                                                    </svg>
                                                                    <span>
                                                {{ Translation::get('pre-order-product-now', 'product', 'Pre order nu, levering op :date:', 'text', [
                                                'date' => $orderProduct->product->expectedInStockDate()
                                            ]) }}
                                            </span>
                                                                </p>
                                                            @endif
                                                        @endif
                                                    @else
                                                        <p class="font-bold text-red-500 text-md flex items-center gap-2">
                                                            <x-lucide-x-circle class="h-5 w-5"/>
                                                            {{ Translation::get('not-in-stock', 'product', 'Niet op voorraad') }}
                                                        </p>
                                                    @endif
                                                </div>

                                                <div
                                                    class="mt-6 flex items-center space-x-4 divide-x divide-gray-200 border-t border-gray-200 pt-4 text-sm font-medium sm:ml-4 sm:mt-0 sm:border-none sm:pt-0">
                                                    <div class="flex flex-1 justify-center">
                                                        <a href="{{$orderProduct->product ? $orderProduct->product->getUrl() : '#'}}"
                                                           class="whitespace-nowrap text-primary-600 hover:text-primary-500">
                                                            {{ Translation::get('view-product', 'orders', 'Bekijk product') }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-container>
