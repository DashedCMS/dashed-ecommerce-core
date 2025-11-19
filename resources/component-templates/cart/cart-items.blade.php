@props(['forceWhite' => false, 'items' => []])
<ul role="list" class="divide-y divide-gray-200">
    @foreach($items as $item)
        <li class="py-6 sm:py-10" wire:key="cart-item-{{ $item->rowId }}">
            <div class="flex">
                <div class="flex-shrink-0">
                    @if($item->model->firstImage)
                        <x-dashed-files::image
                            class="h-24 w-24 rounded-md object-cover object-center sm:h-48 sm:w-48"
                            config="dashed"
                            :mediaId="$item->model->firstImage"
                            :alt="$item->model->name"
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
                                       class="font-bold @if($forceWhite) text-white @else text-primary @endif hover:text-secondary trans">
                                        {{ $item->model->name }}
                                    </a>
                                </h3>
                            </div>

                            @if(count($item->options['options']))
                                <div class="my-2 grid text-sm">
                                    @foreach($item->options['options'] as $option)
                                        @if($loop->first)
                                            <p class="">
                                                {{ $option['name'] . (($option['price'] ?? null) > 0 ? ' ' . Translation::get('product-extra-for-price', 'cart', 'voor :price:', 'text', [
                                                    'price' => \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($option['price'])
                                                ]) : '') . ': ' }}{{ $option['value'] }}
                                            </p>
                                        @else
                                            <p class="pt-2 mt-2 border-t border-gray-200">
                                                {{ $option['name'] . (($option['price'] ?? null) > 0 ? ' ' . Translation::get('product-extra-for-price', 'cart', 'voor :price:', 'text', [
                                                    'price' => \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($option['price'])
                                                ]) : '') . ': ' }}{{ $option['value'] }}
                                            </p>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex flex-wrap gap-2 items-center mt-1">
                                @if($item->options['discountPrice'] && $item->options['discountPrice'] > $item->price)
                                    <span class="line-through text-red-500 mr-2 font-normal">
                                        {{ CurrencyHelper::formatPrice($item->options['discountPrice'] * $item->qty) }}
                                    </span>
                                @endif
                                <p class="text-sm font-bold">
                                    {{ CurrencyHelper::formatPrice($item->price * $item->qty) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div
                                x-data="{
                                    qty: {{ $item->qty }},
                                    timeout: null,
                                    change(delta) {
                                        let newQty = this.qty + delta;

                                        if (newQty < 0) {
                                            newQty = 0;
                                        }

                                        @php $maxStock = $item->model->stock(); @endphp
                                        @if(!is_null($maxStock))
                                            if (newQty > {{ $maxStock }}) {
                                                newQty = {{ $maxStock }};
                                            }
                                        @endif

                                        this.qty = newQty;
                                        this.scheduleUpdate();
                                    },
                                    setQty(value) {
                                        let newQty = parseInt(value ?? 0, 10);
                                        if (isNaN(newQty) || newQty < 0) {
                                            newQty = 0;
                                        }

                                        @if(!is_null($maxStock))
                                            if (newQty > {{ $maxStock }}) {
                                                newQty = {{ $maxStock }};
                                            }
                                        @endif

                                        this.qty = newQty;
                                        this.scheduleUpdate();
                                    },
                                    scheduleUpdate() {
                                        clearTimeout(this.timeout);
                                        this.timeout = setTimeout(() => {
                                            $wire.changeQuantity('{{ $item->rowId }}', this.qty);
                                        }, 300);
                                    }
                                }"
                                class="inline-flex items-center p-1 transition rounded @if($forceWhite) bg-white @else bg-gray-100 @endif focus-within:bg-white focus-within:ring-2 focus-within:ring-secondary"
                            >
                                <button
                                    type="button"
                                    @click="change(-1)"
                                    class="grid w-6 h-6 bg-primary rounded shadow-xl place-items-center text-white hover:text-primary-text shadow-secondary/10 ring-1 ring-black/5"
                                >
                                    <x-lucide-minus class="w-4 h-4"/>
                                </button>

                                <input
                                    class="w-[4ch] px-0 py-0.5 focus:ring-0 text-center bg-transparent border-none text-primary font-bold"
                                    :value="qty"
                                    readonly
                                    min="0"
                                    max="{{ $item->model->stock() }}"
                                >

                                <button
                                    type="button"
                                    @click="change(1)"
                                    class="grid w-6 h-6 bg-primary rounded shadow-xl place-items-center text-white hover:text-primary-text shadow-secondary/10 ring-1 ring-black/5"
                                >
                                    <x-lucide-plus class="w-4 h-4"/>
                                </button>

                                <div class="absolute right-0 top-0">
                                    <button
                                        type="button"
                                        @click="setQty(0)"
                                        class="-m-2 inline-flex p-2 text-white hover:bg-red-500 rounded-full bg-primary trans"
                                    >
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
            </div>

            @if($item->model->is_bundle)
                <div class="mt-4 rounded-lg bg-primary text-white p-4">
                    <h3 class="text-lg font-medium">
                        {{ Translation::get('this-bundel-contains', 'cart', 'Deze bundel bestaat uit:') }}
                    </h3>

                    <div class="grid gap-4 mt-4">
                        @foreach($item->model->bundleProducts as $bundleProduct)
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    @if($bundleProduct->firstImage)
                                        <x-dashed-files::image
                                            class="h-12 w-12 rounded-md object-cover object-center"
                                            config="dashed"
                                            :mediaId="$bundleProduct->firstImage"
                                            :alt="$bundleProduct->name"
                                            :manipulations="[
                                                'widen' => 400,
                                            ]"
                                        />
                                    @endif
                                </div>

                                <div class="ml-4 flex flex-1 flex-col justify-center items-start sm:ml-6">
                                    <div class="relative pr-9 sm:grid sm:gap-x-6 sm:pr-0">
                                        <div>
                                            <div class="flex justify-between">
                                                <h3 class="text-sm pr-6">
                                                    <a href="{{ $bundleProduct->getUrl() }}"
                                                       class="font-bold text-white hover:text-secondary trans">
                                                        {{ $bundleProduct->name }}
                                                    </a>
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </li>
    @endforeach
</ul>
