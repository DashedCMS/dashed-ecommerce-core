@props(['forceWhite' => false, 'items' => []])
<ul role="list" class="divide-y divide-gray-200">
    @foreach($items as $item)
        <li class="flex py-6 sm:py-10">
            <div class="flex-shrink-0">
                @if($item->model->firstImage)
                    <x-drift::image
                        class="h-24 w-24 rounded-md object-cover object-center sm:h-48 sm:w-48"
                        config="dashed"
                        :path="$item->model->firstImage"
                        :alt=" $item->model->name"
                        :manipulations="[
                                                    'widen' => 400,
                                                ]"
                    />
                @endif
            </div>

            <div class="ml-4 flex flex-1 flex-col justify-between sm:ml-6">
                <div class="relative pr-9 sm:grid sm:gap-x-6 sm:pr-0">
                    <div>
                        <div class="flex justify-between">
                            <h3 class="text-sm pr-6">
                                <a href="{{ $item->model->getUrl() }}"
                                   class="font-bold @if($forceWhite) text-white @else text-primary-500 @endif hover:text-primary-800 trans">
                                    {{ $item->model->name }}
                                </a>
                            </h3>
                        </div>
                        @if(count($item->options))
                            <div class="my-2 grid text-sm">
                                @foreach($item->options as $option)
                                    @if($loop->first)
                                        <p class="">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                    @else
                                        <p class="pt-2 mt-2 border-t border-gray-200">{{$option['name'] . ':'}}{{$option['value']}}</p>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <p class="mt-1 text-sm font-bold ">{{CurrencyHelper::formatPrice($item->price * $item->qty)}}</p>
                    </div>

                    <div class="mt-4">
                        <div
                            class="inline-flex items-center p-1 transition rounded @if($forceWhite) bg-white @else bg-gray-100 @endif focus-within:bg-white focus-within:ring-2 focus-within:ring-primary-500">
                            <button
                                wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty - 1 }}')"
                                class="grid w-6 h-6 bg-primary-500 rounded shadow-xl place-items-center text-white hover:bg-primary-500 hover:text-white shadow-primary-500/10 ring-1 ring-black/5"
                            >
                                <x-lucide-minus class="w-4 h-4"/>
                            </button>

                            <input
                                class="w-[4ch] px-0 py-0.5 focus:ring-0 text-center bg-transparent border-none text-primary-500 font-bold"
                                value="{{$item->qty}}"
                                disabled
                                min="0" max="{{$item->model->stock()}}">

                            <button
                                wire:click="changeQuantity('{{ $item->rowId }}', '{{ $item->qty + 1 }}')"
                                class="grid w-6 h-6 bg-primary-500 rounded shadow-xl place-items-center text-white hover:bg-primary-500 hover:text-white shadow-primary-500/10 ring-1 ring-black/5"
                            >
                                <x-lucide-plus class="w-4 h-4"/>
                            </button>

                            <div class="absolute right-0 top-0">
                                <button
                                    wire:click="changeQuantity('{{ $item->rowId }}', '0')"
                                    type="button"
                                    class="-m-2 inline-flex p-2 text-white hover:text-red-500 rounded-full bg-primary-700 trans">
                                    <span class="sr-only">{{ Translation::get('remove', 'cart', 'Verwijder') }}</span>
                                    <x-lucide-trash class="h-5 w-5"/>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex space-x-2 text-sm text-gray-700">
                    <x-product.stock-text :product="$item->model" :forceWhite="$forceWhite ?? false"/>
                </div>
            </div>
        </li>
    @endforeach
</ul>
