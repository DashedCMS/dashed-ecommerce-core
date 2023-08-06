<div class="bg-white py-16 sm:py-24">
    @if(count($this->cartItems))
        <x-container>
            <div class="grid grid-cols-6 gap-8">
                <div class="col-span-6 lg:col-span-4">
                    <h1 class="text-2xl font-bold">{{Translation::get('items-in-cart', 'cart', 'Items in je winkelwagen')}}</h1>
                    <div class="grid grid-cols-12 gap-4 mt-4">
                        <div class="col-span-6">{{Translation::get('product', 'cart', 'Product')}}</div>
                        <div
                            class="col-span-4 hidden lg:block">{{Translation::get('quantity', 'cart', 'Hoeveelheid')}}</div>
                        <div class="col-span-2 hidden lg:block">{{Translation::get('price', 'cart', 'Prijs')}}</div>
                    </div>
                    <hr class="mt-4 border-primary">
                    @foreach($this->cartItems as $item)
                        <div class="grid grid-cols-12 gap-4 border-b border-primary py-4">
                            <div class="flex items-center space-x-4 col-span-12 lg:col-span-6">
                                <button
                                    wire:click="changeQuantity('{{ $item->rowId }}', '0')"
                                    class="border-2 border-primary text-primary-500 hover:text-white hover:bg-primary-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                         xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                                @if($item->model->firstImageUrl)
                                    <x-drift::image
                                        class="mx-auto"
                                        config="dashed"
                                        :path="$item->model->firstImageUrl"
                                        :alt=" $item->model->name"
                                        :manipulations="[
                                                    'widen' => 100,
                                                ]"
                                    />
                                @endif
                                <div class="">
                                    {{$item->model->name}}
                                    @foreach($item->options as $option)
                                        <br>
                                        <small>{{$option['name']}}: {{$option['value']}}</small>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-span-8 lg:col-span-4 flex items-center">
                                <div class="inline-flex items-center h-10 overflow-hidden">
                                    {{--                                    <div wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty + 1 }}')" class="flex items-center justify-center w-10 h-10 bg-gray-50 text-primary-500 cursor-pointer">--}}
                                    {{--                                        +--}}
                                    {{--                                    </div>--}}
                                    <input class="w-16 h-10 text-center text-primary-500 font-bold border-2 border-primary" type="number" value="{{$item->qty}}"
                                           disabled
                                           min="0" max="{{$item->model->stock()}}">
                                    {{--                                    <div wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty - 1 }}')" class="flex items-center justify-center w-10 h-10 bg-gray-50 text-primary-500 cursor-pointer">--}}
                                    {{--                                        ---}}
                                    {{--                                    </div>--}}
                                </div>
                            </div>
                            <div class="col-span-4 lg:col-span-2 flex items-center">
                                {{CurrencyHelper::formatPrice($item->price * $item->qty)}}
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="col-span-6 lg:col-span-2 p-4 bg-primary-500 rounded-md text-white">
                    <h2 class="text-2xl">{{Translation::get('overview', 'cart', 'Overzicht')}}</h2>
                    <hr class="my-4">
                    <div>
                        <form wire:submit.prevent="applyDiscountCode" class="flex justify-between">
                            <label class="block relative h-0 w-0 overflow-hidden">{{Translation::get('add-discount-code', 'cart', 'Voeg kortingscode toe')}}</label>
                            <input placeholder="{{Translation::get('add-discount-code', 'cart', 'Voeg kortingscode toe')}}"
                                   class="w-3/5 xl:w-2/3 block pl-3 pr-10 py-2 text-base border border-primary-500 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm rounded-md"
                                   wire:model.lazy="discountCode">
                            <button type="submit" class="w-2/5 xl:w-1/3 ml-4 lg:ml-2 xl:ml-4 button button-primary-on-white"
                                    aria-label="Apply button">{{Translation::get('add-discount', 'cart', 'Toevoegen')}}</button>
                        </form>
                    </div>
                    <hr class="my-4">
                    <p>{{Translation::get('subtotal', 'cart', 'Subtotaal')}}: <span
                            class="float-right">{{ $subtotal }}</span></p>
                    <hr class="my-2">
                    @if($discount)
                        <p>
                            {{Translation::get('discount', 'cart', 'Korting')}}: <span
                                class="float-right">{{ $discount }}</span>
                        </p>
                        <hr class="my-2">
                    @endif
                    <p>{{Translation::get('btw', 'cart', 'BTW')}}: <span
                            class="float-right">{{ $tax }}</span></p>
                    <hr class="my-2">
                    <p>{{Translation::get('total', 'cart', 'Totaal')}}: <span
                            class="float-right">{{ $total }}</span></p>

                    <div class="flex">
                        <a href="{{ShoppingCart::getCheckoutUrl()}}"
                           class="button button-primary-on-white mt-5 py-2 px-2 w-full uppercase text-center">
                            {{Translation::get('proceed-to-checkout', 'cart', 'Proceed to checkout')}}
                        </a>
                    </div>
                </div>
            </div>
        </x-container>
    @else
        <x-container>
            <h1 class="text-2xl font-bold">{{Translation::get('no-items-in-cart', 'cart', 'No items in your cart?')}}</h1>
            <p>{{Translation::get('go-shop-furter', 'cart', 'Continue shopping!')}}</p>
        </x-container>
    @endif

</div>
