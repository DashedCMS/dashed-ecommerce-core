<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Gedeelde verlanglijst') }}</title>
</head>
<body>
    <div class="container mx-auto px-4 py-12">
        <h1 class="text-2xl font-bold">{{ __('Gedeelde verlanglijst') }}</h1>
        @if($items->isEmpty())
            <p class="mt-6">{{ __('Deze lijst is leeg.') }}</p>
        @else
            <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach($items as $item)
                    @php($product = $item->product)
                    <a href="{{ $product->getUrl() }}" class="rounded border p-4">
                        @if($product->firstImage)
                            <x-dashed-files::image config="dashed" :mediaId="$product->firstImage" :alt="$product->name" :manipulations="['fit' => [400, 400]]" class="aspect-square w-full object-contain"/>
                        @endif
                        <h2 class="mt-3 font-semibold">{{ $product->name }}</h2>
                        <p>{{ \Dashed\DashedEcommerceCore\Classes\CurrencyHelper::formatPrice($product->currentPrice) }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
